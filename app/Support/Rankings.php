<?php

namespace App\Support;

use App\Models\Discipline;
use App\Models\EventResult;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * Standings from results recorded on this site. This is not a
 * federation or SAPRF classification.
 */
final class Rankings
{
    /**
     * @return Collection<int, object{user: User, starts: int, wins: int, podiums: int}>
     */
    public function forDiscipline(Discipline $discipline, ?int $year = null): Collection
    {
        $year ??= (int) now()->year;

        $rows = EventResult::query()
            ->select('user_id')
            ->selectRaw('COUNT(*) as starts')
            ->selectRaw('SUM(CASE WHEN placing = 1 THEN 1 ELSE 0 END) as wins')
            ->selectRaw('SUM(CASE WHEN placing BETWEEN 1 AND 3 THEN 1 ELSE 0 END) as podiums')
            ->where('discipline_id', $discipline->id)
            ->whereNotNull('user_id')
            ->whereHas('event', fn ($query) => $query->whereYear('starts_at', $year))
            ->groupBy('user_id')
            ->orderByDesc('wins')
            ->orderByDesc('podiums')
            ->orderByDesc('starts')
            ->get();

        $users = User::query()->whereIn('id', $rows->pluck('user_id'))->get()->keyBy('id');

        return $rows
            ->map(function (EventResult $row) use ($users): ?object {
                $user = $users->get($row->user_id);

                if (! $user instanceof User) {
                    return null;
                }

                $user->ensureCalendarSlug();

                return (object) [
                    'user' => $user,
                    'starts' => (int) $row->starts,
                    'wins' => (int) $row->wins,
                    'podiums' => (int) $row->podiums,
                ];
            })
            ->filter()
            ->values();
    }

    /**
     * @return array{starts: int, wins: int, podiums: int, disciplines: Collection<int, Discipline>}
     */
    public function profile(User $user): array
    {
        $results = EventResult::query()
            ->where('user_id', $user->id)
            ->with('discipline')
            ->get();

        return [
            'starts' => $results->count(),
            'wins' => $results->filter(fn (EventResult $result): bool => $result->isWin())->count(),
            'podiums' => $results->filter(fn (EventResult $result): bool => $result->isPodium())->count(),
            'disciplines' => $results->pluck('discipline')->filter()->unique('id')->values(),
        ];
    }
}
