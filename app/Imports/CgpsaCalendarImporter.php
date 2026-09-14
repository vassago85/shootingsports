<?php

namespace App\Imports;

use App\Enums\EventLevel;
use App\Enums\EventStatus;
use App\Enums\OrganisationType;
use App\Enums\Province;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;

class CgpsaCalendarImporter
{
    public function __construct(private CalendarImporter $importer) {}

    /**
     * @return array{created: int, updated: int, skipped: int}
     */
    public function import(): array
    {
        $payload = Http::timeout(20)
            ->withUserAgent('ShootingSportsBot/1.0 (+https://shootingsports.co.za)')
            ->get('https://cgpsa.co.za/wp-json/tribe/events/v1/events', [
                'per_page' => 100,
                'start_date' => now('Africa/Johannesburg')->startOfYear()->toDateTimeString(),
                'end_date' => now('Africa/Johannesburg')->addYears(2)->endOfYear()->toDateTimeString(),
                'status' => 'publish',
            ])
            ->throw()
            ->json();

        $host = $this->importer->federation(
            'cgpsa',
            'Central Gauteng Practical Shooting Association',
            'https://cgpsa.co.za',
            OrganisationType::ProvincialBody,
        );

        $host->fill([
            'province' => Province::Gauteng,
            'town' => 'Alberton',
        ])->save();

        return $this->importer->persist($host, $this->parse(is_array($payload) ? $payload : []));
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return list<ImportedMatch>
     */
    public function parse(array $payload): array
    {
        $matches = [];

        foreach ($payload['events'] ?? [] as $event) {
            if (! is_array($event)) {
                continue;
            }

            $parsed = $this->event($event);

            if ($parsed) {
                $matches[] = $parsed;
            }
        }

        return $matches;
    }

    /**
     * @param  array<string, mixed>  $event
     */
    private function event(array $event): ?ImportedMatch
    {
        $id = $event['id'] ?? null;
        $title = trim(html_entity_decode((string) ($event['title'] ?? ''), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        $url = trim((string) ($event['url'] ?? ''));
        $startsAt = $this->date($event['start_date'] ?? null);

        if (! is_numeric($id) || $title === '' || $url === '' || ! $startsAt) {
            return null;
        }

        $endsAt = $this->date($event['end_date'] ?? null);

        if ($endsAt && $endsAt->lte($startsAt)) {
            $endsAt = null;
        }

        $venue = is_array($event['venue'] ?? null) ? $event['venue'] : [];
        $venueName = trim((string) ($venue['venue'] ?? ''));

        return new ImportedMatch(
            externalId: 'cgpsa-event-'.$id,
            title: $title,
            entryUrl: $url,
            startsAt: $startsAt,
            endsAt: $endsAt,
            level: $this->level($title, $event['categories'] ?? []),
            status: $startsAt->isPast() ? EventStatus::Completed : EventStatus::Confirmed,
            province: Province::Gauteng,
            venueName: $venueName !== '' ? $venueName : 'Golden City Shooting Club',
            disciplineSlug: $this->discipline($title),
            description: filled($event['description'] ?? null)
                ? trim(html_entity_decode(strip_tags((string) $event['description']), ENT_QUOTES | ENT_HTML5, 'UTF-8'))
                : null,
            venueTown: filled($venue['city'] ?? null) ? trim((string) $venue['city']) : 'Alberton',
        );
    }

    private function date(mixed $value): ?Carbon
    {
        if (! is_string($value) || trim($value) === '') {
            return null;
        }

        try {
            return Carbon::parse($value, 'Africa/Johannesburg')->startOfDay();
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * @param  list<mixed>  $categories
     */
    private function level(string $title, array $categories): EventLevel
    {
        $haystack = strtolower($title.' '.collect($categories)->pluck('slug')->implode(' '));

        return match (true) {
            str_contains($haystack, 'league') => EventLevel::Series,
            default => EventLevel::Club,
        };
    }

    private function discipline(string $title): string
    {
        $title = strtolower($title);

        return match (true) {
            str_contains($title, 'steel') => 'steel-challenge',
            str_contains($title, 'shotgun') => 'sporting-clays',
            default => 'ipsc-practical',
        };
    }
}
