<?php

namespace App\Imports;

use App\Enums\EventLevel;
use App\Enums\EventStatus;
use App\Enums\ListingSource;
use App\Enums\Province;
use App\Models\Event;
use App\Support\VenueNames;
use DOMDocument;
use DOMElement;
use DOMXPath;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class SaprfCalendarImporter
{
    public function __construct(private CalendarImporter $importer) {}

    /**
     * @return array{created: int, updated: int, skipped: int}
     */
    public function import(): array
    {
        $html = Http::timeout(20)
            ->withUserAgent('ShootingSportsBot/1.0 (+https://shootingsports.co.za)')
            ->get('https://saprf.co.za/events?view=table')
            ->throw()
            ->body();

        $host = $this->importer->federation(
            'saprf',
            'South African Precision Rifle Federation',
            'https://saprf.co.za',
        );

        $matches = $this->parse($html);
        $result = $this->importer->persist($host, $matches);
        $result['updated'] += $this->refreshDelisted(array_map(fn (ImportedMatch $match): string => $match->entryUrl, $matches));

        return $result;
    }

    /**
     * SAPRF drops cancelled and postponed matches from the events table
     * instead of flagging them, so any upcoming imported match missing
     * from the table gets its status re-read from its own detail page.
     *
     * @param  list<string>  $listedUrls
     */
    private function refreshDelisted(array $listedUrls): int
    {
        $refreshed = 0;

        $delisted = Event::query()
            ->where('source', ListingSource::Import)
            ->where('entry_url', 'like', 'https://saprf.co.za/events/%')
            ->whereNotIn('entry_url', $listedUrls)
            ->whereNotIn('status', [EventStatus::Cancelled, EventStatus::Postponed, EventStatus::Completed])
            ->where('starts_at', '>=', now()->startOfDay())
            ->get();

        foreach ($delisted as $event) {
            $response = Http::timeout(20)
                ->withUserAgent('ShootingSportsBot/1.0 (+https://shootingsports.co.za)')
                ->get($event->entry_url);

            if (! $response->successful()) {
                continue;
            }

            $status = $this->detailStatus($response->body());

            if ($status === null) {
                continue;
            }

            $event->forceFill([
                'status' => $status,
                'last_verified_at' => now(),
            ])->save();
            $refreshed++;
        }

        return $refreshed;
    }

    public function detailStatus(string $html): ?EventStatus
    {
        $dom = new DOMDocument;
        @$dom->loadHTML($html);
        $badges = (new DOMXPath($dom))->query('(//h1)[1]/preceding::span');

        if ($badges === false) {
            return null;
        }

        foreach ($badges as $badge) {
            $label = strtolower(trim($badge->textContent));

            if ($label === 'cancelled' || $label === 'postponed') {
                return $this->status($label);
            }
        }

        return null;
    }

    /**
     * @return list<ImportedMatch>
     */
    public function parse(string $html): array
    {
        $dom = new DOMDocument;
        @$dom->loadHTML($html);
        $xpath = new DOMXPath($dom);
        $rows = $xpath->query('//table//tbody/tr');
        $matches = [];

        if ($rows === false) {
            return [];
        }

        foreach ($rows as $row) {
            if (! $row instanceof DOMElement) {
                continue;
            }

            $parsed = $this->row($xpath, $row);

            if ($parsed) {
                $matches[] = $parsed;
            }
        }

        return $matches;
    }

    private function row(DOMXPath $xpath, DOMElement $row): ?ImportedMatch
    {
        $link = $xpath->query('.//a[contains(@href, "/events/")]', $row)->item(0);

        if (! $link instanceof DOMElement) {
            return null;
        }

        $href = trim($link->getAttribute('href'));

        if (! preg_match('#/events/(\d+)#', $href, $idMatch)) {
            return null;
        }

        $cells = [];

        foreach ($row->getElementsByTagName('td') as $cell) {
            $cells[] = trim(preg_replace('/\s+/', ' ', $cell->textContent) ?? '');
        }

        if (count($cells) < 5) {
            return null;
        }

        [$startsAt, $endsAt] = $this->dates($cells[0]);

        if (! $startsAt) {
            return null;
        }

        $title = trim($link->textContent);
        $discipline = $this->discipline($cells[2] ?? '');
        $level = EventLevel::tryFrom(strtolower($cells[3] ?? '')) ?? EventLevel::Club;
        $province = Province::fromCode($cells[4] ?? '')
            ?? Province::tryFrom(strtolower(str_replace(' ', '_', $cells[4] ?? '')));

        $venueName = filled($cells[5] ?? null) ? $cells[5] : null;
        $needsReview = false;
        $importNotes = null;

        // "N-Day" / "N Day" titles with a single-date source cell have no
        // end date on the SAPRF table. Infer ends_at from the title and
        // flag the row so staff can confirm before publication.
        if ($endsAt === null) {
            $inferredDays = $this->inferMultiDayFromTitle($title);

            if ($inferredDays !== null) {
                $endsAt = $startsAt->copy()->addDays($inferredDays - 1);
                $needsReview = true;
                $importNotes = sprintf(
                    'End date inferred from title (%d-day match): %s',
                    $inferredDays,
                    $endsAt->format('j M Y'),
                );
            }
        }

        // Multi-venue strings like "Day 1 - Papaberg / Day 2 - Legends"
        // are not a single venue. Leave venue_id null so the importer
        // does not create a junk row and surface the raw string to staff.
        if ($venueName !== null && $this->looksMultiVenue($venueName)) {
            $raw = $venueName;
            $venueName = null;
            $needsReview = true;
            $importNotes = trim(($importNotes !== null ? $importNotes."\n" : '')
                .'Multi-venue source string, left unlinked: '.$raw);
        }

        return new ImportedMatch(
            externalId: 'saprf-event-'.$idMatch[1],
            title: $title,
            entryUrl: Str::startsWith($href, 'http') ? $href : 'https://saprf.co.za'.$href,
            startsAt: $startsAt,
            endsAt: $endsAt,
            level: $level,
            status: $this->status($cells[7] ?? $cells[6] ?? ''),
            province: $province,
            venueName: $venueName,
            disciplineSlug: $discipline,
            needsReview: $needsReview,
            importNotes: $importNotes,
        );
    }

    /**
     * Pulls the day count out of titles like "Rimfire 2-Day National",
     * "Centrefire 3 Day Nationals", "PRS 2Day". Sane upper bound keeps
     * false matches out — anything above a two-week match is rejected.
     */
    private function inferMultiDayFromTitle(string $title): ?int
    {
        if (preg_match('/(?<!\d)(\d{1,2})\s*-?\s*day\b/i', $title, $match) !== 1) {
            return null;
        }

        $days = (int) $match[1];

        return ($days >= 2 && $days <= 14) ? $days : null;
    }

    /**
     * Multi-venue markers in the SAPRF source. A single real venue name
     * never contains a slash or "Day N" — those are editor shorthand for
     * two or more venues on different days.
     */
    private function looksMultiVenue(string $name): bool
    {
        return VenueNames::isScheduleFragment($name);
    }

    /**
     * @return array{0: ?Carbon, 1: ?Carbon}
     */
    private function dates(string $value): array
    {
        $value = str_replace(['–', '—'], '-', $value);

        if (preg_match('/^(\d{1,2})\s*-\s*(\d{1,2})\s+([A-Za-z]+)\s+(\d{4})$/', $value, $match)) {
            $start = Carbon::parse($match[1].' '.$match[3].' '.$match[4], 'Africa/Johannesburg')->startOfDay();
            $end = Carbon::parse($match[2].' '.$match[3].' '.$match[4], 'Africa/Johannesburg')->startOfDay();

            return [$start, $end];
        }

        try {
            $start = Carbon::parse($value, 'Africa/Johannesburg')->startOfDay();
        } catch (\Throwable) {
            return [null, null];
        }

        return [$start, null];
    }

    private function status(string $label): EventStatus
    {
        $label = strtolower($label);

        return match (true) {
            str_contains($label, 'cancel') => EventStatus::Cancelled,
            str_contains($label, 'postpone') => EventStatus::Postponed,
            str_contains($label, 'full') => EventStatus::Full,
            str_contains($label, 'open') => EventStatus::EntriesOpen,
            default => EventStatus::Confirmed,
        };
    }

    private function discipline(string $label): ?string
    {
        $label = strtolower($label);

        return match (true) {
            str_contains($label, 'pr22'), str_contains($label, 'rimfire') => 'pr22-rimfire',
            str_contains($label, 'prs'), str_contains($label, 'centrefire'), str_contains($label, 'centerfire') => 'prs',
            default => 'precision-rifle',
        };
    }
}
