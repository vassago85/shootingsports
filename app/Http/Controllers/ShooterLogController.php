<?php

namespace App\Http\Controllers;

use App\Models\AttendedEvent;
use App\Models\Discipline;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ShooterLogController extends Controller
{
    /**
     * The shooter's personal log — reverse-chron table with year and
     * discipline filters, per-discipline totals for the current year,
     * and the export CTAs (Pro-gated in the view).
     */
    public function index(Request $request): View
    {
        $user = auth()->user();
        abort_unless($user instanceof User, 401);

        $year = (int) $request->query('year', now()->year);
        $disciplineId = $request->query('discipline') ? (int) $request->query('discipline') : null;

        $query = AttendedEvent::query()
            ->with('discipline')
            ->where('user_id', $user->id)
            ->orderByDesc('event_date')
            ->orderByDesc('id');

        if ($year > 0) {
            $query->whereYear('event_date', $year);
        }

        if ($disciplineId !== null) {
            $query->where('discipline_id', $disciplineId);
        }

        $entries = $query->get();

        // Bucket by discipline for the summary widget. Snapshot name
        // is the source of truth so historical entries that reference
        // a since-deleted discipline still show cleanly.
        $totals = $entries
            ->groupBy(fn (AttendedEvent $e): string => $e->discipline_name_snapshot ?? 'Uncategorised')
            ->map->count()
            ->sortDesc();

        $currentCount = AttendedEvent::query()->where('user_id', $user->id)->count();

        return view('public.shooters.log', [
            'user' => $user,
            'entries' => $entries,
            'totals' => $totals,
            'currentCount' => $currentCount,
            'remainingSlots' => $user->remaining('attended_events_slots', $currentCount),
            'availableYears' => $this->availableYears($user),
            'availableDisciplines' => $this->availableDisciplines(),
            'filters' => [
                'year' => $year,
                'discipline' => $disciplineId,
            ],
        ]);
    }

    public function destroy(AttendedEvent $attendedEvent): RedirectResponse
    {
        $user = auth()->user();
        abort_unless($user instanceof User, 401);
        abort_unless($attendedEvent->user_id === $user->id, 403);

        $attendedEvent->delete();

        return back()->with('status', 'Match removed from your log.');
    }

    /**
     * CSV export. Pro-only per the export-attendance-log gate. Streams
     * so a shooter with hundreds of matches does not hold the whole
     * export in memory.
     */
    public function exportCsv(Request $request): StreamedResponse
    {
        $user = auth()->user();
        abort_unless($user instanceof User, 401);
        abort_unless(auth()->user()?->can('export-attendance-log'), 403);

        $year = (int) $request->query('year', now()->year);

        $entries = AttendedEvent::query()
            ->where('user_id', $user->id)
            ->when($year > 0, fn ($q) => $q->whereYear('event_date', $year))
            ->orderBy('event_date')
            ->get();

        $filename = 'attendance-'.$year.'-'.$user->id.'.csv';

        return response()->streamDownload(function () use ($entries): void {
            $out = fopen('php://output', 'wb');

            fputcsv($out, [
                'Date', 'Match', 'Discipline', 'Host', 'Venue',
                'Division', 'Class', 'Placing', 'Field size', 'Score', 'Notes',
            ]);

            foreach ($entries as $e) {
                fputcsv($out, [
                    $e->event_date?->format('Y-m-d'),
                    $e->event_name_snapshot,
                    $e->discipline_name_snapshot,
                    $e->host_snapshot,
                    $e->venue_snapshot,
                    $e->division,
                    $e->classification,
                    $e->placing,
                    $e->field_size,
                    $e->score,
                    $e->notes,
                ]);
            }

            fclose($out);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    /**
     * Annual attendance record — a print-optimised HTML page the
     * shooter saves as PDF via the browser (Cmd/Ctrl+P → Save as PDF).
     * No server-side PDF library; the print CSS in the view handles
     * page breaks, margins, and the signature line.
     *
     * Pro-only per the export-attendance-log gate.
     */
    public function printable(Request $request): View
    {
        $user = auth()->user();
        abort_unless($user instanceof User, 401);
        abort_unless(auth()->user()?->can('export-attendance-log'), 403);

        $year = (int) $request->query('year', now()->year);

        $entries = AttendedEvent::query()
            ->where('user_id', $user->id)
            ->whereYear('event_date', $year)
            ->orderBy('event_date')
            ->get();

        $totals = $entries
            ->groupBy(fn (AttendedEvent $e): string => $e->discipline_name_snapshot ?? 'Uncategorised')
            ->map->count()
            ->sortDesc();

        return view('public.shooters.log-print', [
            'user' => $user,
            'year' => $year,
            'entries' => $entries,
            'totals' => $totals,
            'generatedAt' => now(),
        ]);
    }

    /**
     * @return list<int> descending list of years in which the user
     *                   logged at least one match, plus the current
     *                   year (so the filter always offers "this year")
     *
     * DB-agnostic — SQLite uses strftime, Postgres uses EXTRACT, and
     * a single SELECT + PHP-side dedupe sidesteps both dialects.
     */
    private function availableYears(User $user): array
    {
        $dates = AttendedEvent::query()
            ->where('user_id', $user->id)
            ->pluck('event_date');

        $years = $dates
            ->map(fn ($d): int => (int) $d->format('Y'))
            ->push(now()->year)
            ->unique()
            ->sortDesc()
            ->values()
            ->all();

        return $years;
    }

    /**
     * @return Collection<int, Discipline>
     */
    private function availableDisciplines(): Collection
    {
        return Discipline::query()
            ->where('is_published', true)
            ->orderBy('name')
            ->get(['id', 'name']);
    }
}
