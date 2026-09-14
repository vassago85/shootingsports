<?php

namespace Database\Seeders;

use App\Enums\EventLevel;
use App\Enums\EventStatus;
use App\Enums\ListingSource;
use App\Enums\ListingStatus;
use App\Enums\OrganisationType;
use App\Enums\Province;
use App\Enums\VenueAccess;
use App\Enums\VerificationState;
use App\Models\Discipline;
use App\Models\Event;
use App\Models\Organisation;
use App\Models\Venue;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Storage;

class SaprfEventsSeeder extends Seeder
{
    public function run(): void
    {
        $prs = Discipline::query()->where('slug', 'prs')->firstOrFail();
        $pr22 = Discipline::query()->where('slug', 'pr22-rimfire')->firstOrFail();
        $precision = Discipline::query()->where('slug', 'precision-rifle')->first();

        $federation = Organisation::query()->updateOrCreate(
            ['slug' => 'saprf'],
            [
                'name' => 'South African Precision Rifle Federation',
                'short_name' => 'SAPRF',
                'type' => OrganisationType::Federation,
                'province' => null,
                'town' => null,
                'website_url' => 'https://saprf.co.za',
                'description' => 'The official competition platform for PRS and PR22 precision rifle — memberships, match management, scoring, and national standings.',
                'logo_path' => $this->storeLogo(),
                'accredited' => true,
                'visitors_welcome' => true,
                'status' => ListingStatus::Published,
                'verification_state' => VerificationState::Unconfirmed,
                'source' => ListingSource::Import,
            ],
        );

        $disciplineIds = array_values(array_filter([
            $precision?->id,
            $prs->id,
            $pr22->id,
        ]));
        $federation->syncDisciplines($disciplineIds, $precision?->id ?? $prs->id);

        $venues = $this->venues();

        foreach ($this->matches() as $match) {
            $discipline = $match['discipline'] === 'pr22' ? $pr22 : $prs;
            $venue = $venues[$match['venue']];

            $event = Event::query()->updateOrCreate(
                ['slug' => $match['slug']],
                [
                    'title' => $match['title'],
                    'host_organisation_id' => $federation->id,
                    'venue_id' => $venue->id,
                    'starts_at' => $match['starts_at'],
                    'ends_at' => $match['ends_at'],
                    'all_day' => true,
                    'level' => $match['level'],
                    'status' => $match['status'],
                    'confirmed_at' => now(),
                    'entry_fee_cents' => $match['entry_fee_cents'],
                    'member_fee_cents' => $match['member_fee_cents'],
                    'entry_url' => $match['entry_url'],
                    'capacity' => $match['capacity'],
                    'entries_taken' => $match['entries_taken'],
                    'description' => $match['description'],
                    'source' => ListingSource::Import,
                    'last_verified_at' => now(),
                ],
            );

            $event->syncDisciplines([$discipline->id], $discipline->id);
        }
    }

    /**
     * @return array<string, Venue>
     */
    private function venues(): array
    {
        $rows = [
            'balmoral-farm-mpumalanga' => [
                'name' => 'Balmoral Farm',
                'province' => Province::Mpumalanga,
                'town' => 'Balmoral',
            ],
            'darling-steel-valley' => [
                'name' => 'Darling Steel Valley',
                'province' => Province::WesternCape,
                'town' => 'Darling',
            ],
            'atlantis-shooting-range' => [
                'name' => 'Atlantis Shooting Range',
                'province' => Province::WesternCape,
                'town' => 'Atlantis',
            ],
            'papaberg-legends-rayton' => [
                'name' => 'Papaberg / Legends Adventure Farm',
                'province' => Province::Gauteng,
                'town' => 'Rayton',
                'notes' => 'PR22 GP 2-day: Day 1 Papaberg, Day 2 Legends Adventure Farm.',
            ],
            'vlakkenheuwel-hermon' => [
                'name' => 'Vlakkenheuwel',
                'province' => Province::WesternCape,
                'town' => 'Hermon',
            ],
            'legends-adventure-farm' => [
                'name' => 'Legends Adventure Farm',
                'province' => Province::Gauteng,
                'town' => 'Gauteng',
            ],
        ];

        $venues = [];

        foreach ($rows as $slug => $attributes) {
            $venues[$slug] = Venue::query()->updateOrCreate(
                ['slug' => $slug],
                [
                    'name' => $attributes['name'],
                    'province' => $attributes['province'],
                    'town' => $attributes['town'],
                    'notes' => $attributes['notes'] ?? null,
                    'access' => VenueAccess::GuestByArrangement,
                    'status' => ListingStatus::Published,
                    'verification_state' => VerificationState::Unconfirmed,
                    'source' => ListingSource::Import,
                ],
            );
        }

        return $venues;
    }

    /**
     * Upcoming SAPRF matches from https://saprf.co.za/events (scraped 2026-09-14).
     *
     * @return list<array<string, mixed>>
     */
    private function matches(): array
    {
        return [
            [
                'slug' => 'saprf-pr22-mp-provincial-2026',
                'title' => 'Rimfire PR22 MP Provincial',
                'venue' => 'balmoral-farm-mpumalanga',
                'starts_at' => '2026-10-17 08:00:00',
                'ends_at' => null,
                'level' => EventLevel::Provincial,
                'status' => EventStatus::EntriesOpen,
                'discipline' => 'pr22',
                'entry_fee_cents' => 90000,
                'member_fee_cents' => 70000,
                'entry_url' => 'https://saprf.co.za/events/111',
                'capacity' => null,
                'entries_taken' => null,
                'description' => 'PR22 provincial open. Match director: Eddie Kinnear. Entries on SAPRF.',
            ],
            [
                'slug' => 'saprf-prs-wc-2-day-national-2026',
                'title' => 'Centrefire WC 2-Day National',
                'venue' => 'darling-steel-valley',
                'starts_at' => '2026-10-24 08:00:00',
                'ends_at' => '2026-10-25 17:00:00',
                'level' => EventLevel::National,
                'status' => EventStatus::EntriesOpen,
                'discipline' => 'prs',
                'entry_fee_cents' => 170000,
                'member_fee_cents' => 150000,
                'entry_url' => 'https://saprf.co.za/events/104',
                'capacity' => null,
                'entries_taken' => null,
                'description' => 'Featured PRS national. Match director: Hendrie Brink. Entries on SAPRF.',
            ],
            [
                'slug' => 'saprf-prs-centrefire-1-day-darling-2026',
                'title' => 'CentreFire 1 Day',
                'venue' => 'darling-steel-valley',
                'starts_at' => '2026-10-24 08:00:00',
                'ends_at' => null,
                'level' => EventLevel::Provincial,
                'status' => EventStatus::EntriesOpen,
                'discipline' => 'prs',
                'entry_fee_cents' => 95000,
                'member_fee_cents' => 75000,
                'entry_url' => 'https://saprf.co.za/events/117',
                'capacity' => null,
                'entries_taken' => null,
                'description' => 'PRS provincial open at Darling Steel Valley. Match director: Hendrie Brink. Entries on SAPRF.',
            ],
            [
                'slug' => 'saprf-pr22-wc-provincial-champs-2026',
                'title' => 'WC Provincial Rimfire Champs',
                'venue' => 'atlantis-shooting-range',
                'starts_at' => '2026-10-31 08:00:00',
                'ends_at' => null,
                'level' => EventLevel::Provincial,
                'status' => EventStatus::EntriesOpen,
                'discipline' => 'pr22',
                'entry_fee_cents' => 80000,
                'member_fee_cents' => 60000,
                'entry_url' => 'https://saprf.co.za/events/112',
                'capacity' => null,
                'entries_taken' => null,
                'description' => 'PR22 provincial open at Atlantis Shooting Range. Match director: Handre van Niekerk. Entries on SAPRF.',
            ],
            [
                'slug' => 'saprf-pr22-gp-2-day-national-2026',
                'title' => 'Rimfire PR22 GP 2-Day National Championship Match',
                'venue' => 'papaberg-legends-rayton',
                'starts_at' => '2026-11-07 08:00:00',
                'ends_at' => '2026-11-08 17:00:00',
                'level' => EventLevel::National,
                'status' => EventStatus::EntriesOpen,
                'discipline' => 'pr22',
                'entry_fee_cents' => 130000,
                'member_fee_cents' => 110000,
                'entry_url' => 'https://saprf.co.za/events/113',
                'capacity' => null,
                'entries_taken' => null,
                'description' => 'Featured PR22 national. Day 1 Papaberg, Day 2 Legends Adventure Farm, Rayton. Match director: Eddie Kinnear. Entries on SAPRF.',
            ],
            [
                'slug' => 'saprf-prs-wc-provincial-champs-2026',
                'title' => 'WC Provincial Champs',
                'venue' => 'vlakkenheuwel-hermon',
                'starts_at' => '2026-11-14 08:00:00',
                'ends_at' => null,
                'level' => EventLevel::Provincial,
                'status' => EventStatus::EntriesOpen,
                'discipline' => 'prs',
                'entry_fee_cents' => 85000,
                'member_fee_cents' => 65000,
                'entry_url' => 'https://saprf.co.za/events/121',
                'capacity' => 40,
                'entries_taken' => 0,
                'description' => 'PRS provincial open at Vlakkenheuwel, Hermon. Match director: Tony van Heerden. Entries on SAPRF.',
            ],
            [
                'slug' => 'saprf-prs-gp-2-day-national-2026',
                'title' => 'Centrefire GP 2-Day National Championship Match',
                'venue' => 'legends-adventure-farm',
                'starts_at' => '2026-11-21 08:00:00',
                'ends_at' => '2026-11-22 17:00:00',
                'level' => EventLevel::National,
                'status' => EventStatus::Confirmed,
                'discipline' => 'prs',
                'entry_fee_cents' => null,
                'member_fee_cents' => null,
                'entry_url' => 'https://saprf.co.za/events/102',
                'capacity' => null,
                'entries_taken' => null,
                'description' => 'PRS final at Legends Adventure Farm. Match director: Dirk Pio. Entries not open yet on SAPRF.',
            ],
        ];
    }

    private function storeLogo(): ?string
    {
        $source = database_path('seeders/data/saprf-logo.png');

        if (! is_file($source)) {
            return Organisation::query()->where('slug', 'saprf')->value('logo_path');
        }

        $path = 'organisation-logos/saprf.png';
        Storage::disk('media')->put($path, file_get_contents($source));

        return $path;
    }
}
