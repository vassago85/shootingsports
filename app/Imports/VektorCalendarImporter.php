<?php

namespace App\Imports;

use App\Enums\EventLevel;
use App\Enums\EventStatus;
use App\Enums\OrganisationType;
use App\Enums\Province;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;

/**
 * Vektor Shooting Club (Centurion) — The Events Calendar REST API.
 *
 * Same Tribe feed shape as CGPSA. Events are monthly Handgun / Rifle&PCC /
 * Shotgun club shoots. No venue object in the payload, so we default to
 * the club's own Centurion range. Fees are not in the feed (members free,
 * visitors R150 on the day) — we deliberately leave entry_fee_cents null.
 */
class VektorCalendarImporter
{
    public function __construct(private CalendarImporter $importer) {}

    /**
     * @return array{created: int, updated: int, skipped: int}
     */
    public function import(): array
    {
        $payload = Http::timeout(20)
            ->withUserAgent('ShootingSportsBot/1.0 (+https://shootingsports.co.za)')
            ->get('https://www.vektor.co.za/wp-json/tribe/events/v1/events', [
                'per_page' => 100,
                'start_date' => now('Africa/Johannesburg')->startOfYear()->toDateTimeString(),
                'end_date' => now('Africa/Johannesburg')->addYears(2)->endOfYear()->toDateTimeString(),
                'status' => 'publish',
            ])
            ->throw()
            ->json();

        $host = $this->importer->federation(
            'vektor',
            'Vektor Shooting Club',
            'https://www.vektor.co.za',
            OrganisationType::Club,
        );

        $host->fill([
            'province' => Province::Gauteng,
            'town' => 'Centurion',
            'short_name' => 'Vektor',
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
        $venueTown = filled($venue['city'] ?? null) ? trim((string) $venue['city']) : 'Centurion';

        return new ImportedMatch(
            externalId: 'vektor-event-'.$id,
            title: $title,
            entryUrl: $url,
            startsAt: $startsAt,
            endsAt: $endsAt,
            level: EventLevel::Club,
            status: $startsAt->isPast() ? EventStatus::Completed : EventStatus::Confirmed,
            province: Province::Gauteng,
            venueName: $venueName !== '' ? $venueName : 'Vektor Shooting Club',
            disciplineSlug: $this->discipline($title),
            description: filled($event['description'] ?? null)
                ? trim(html_entity_decode(strip_tags((string) $event['description']), ENT_QUOTES | ENT_HTML5, 'UTF-8'))
                : null,
            venueTown: $venueTown,
        );
    }

    private function date(mixed $value): ?Carbon
    {
        if (! is_string($value) || trim($value) === '') {
            return null;
        }

        try {
            return Carbon::parse($value, 'Africa/Johannesburg');
        } catch (\Throwable) {
            return null;
        }
    }

    private function discipline(string $title): string
    {
        $title = strtolower($title);

        return match (true) {
            str_contains($title, 'shotgun') => 'sporting-clays',
            str_contains($title, 'rifle'), str_contains($title, 'pcc') => 'multigun',
            default => 'ipsc-practical',
        };
    }
}
