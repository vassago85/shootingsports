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

class CtsasaEventsSeeder extends Seeder
{
    public function run(): void
    {
        $trap = Discipline::query()->where('slug', 'trap')->firstOrFail();
        $skeet = Discipline::query()->where('slug', 'skeet')->firstOrFail();
        $sporting = Discipline::query()->where('slug', 'sporting-clays')->firstOrFail();

        $federation = Organisation::query()->updateOrCreate(
            ['slug' => 'ctsasa'],
            [
                'name' => 'Clay Target Shooting Association of South Africa',
                'short_name' => 'CTSASA',
                'type' => OrganisationType::Federation,
                'province' => Province::WesternCape,
                'town' => 'Mossel Bay',
                'email' => 'info@ctsasa.co.za',
                'phone' => '+27 (0)44 620 4178',
                'website_url' => 'https://ctsasa.co.za',
                'description' => 'National governing body for clay target shooting — trap, skeet, English Sporting, FITASC, Compak and Olympic shotgun.',
                'logo_path' => $this->storeLogo(),
                'accredited' => true,
                'visitors_welcome' => true,
                'status' => ListingStatus::Published,
                'verification_state' => VerificationState::Unconfirmed,
                'source' => ListingSource::Import,
            ],
        );

        $federation->syncDisciplines(
            [$sporting->id, $trap->id, $skeet->id],
            $sporting->id,
        );

        $venues = $this->venues();
        $disciplineMap = [
            'trap' => $trap,
            'skeet' => $skeet,
            'sporting-clays' => $sporting,
        ];

        foreach ($this->matches() as $match) {
            $venue = $venues[$match['venue']];
            $disciplineIds = array_map(
                fn (string $slug): int => $disciplineMap[$slug]->id,
                $match['disciplines'],
            );

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
                    'entry_url' => $match['entry_url'],
                    'description' => $match['description'],
                    'source' => ListingSource::Import,
                    'last_verified_at' => now(),
                ],
            );

            $event->syncDisciplines($disciplineIds, $disciplineIds[0]);
        }
    }

    /**
     * @return array<string, Venue>
     */
    private function venues(): array
    {
        $rows = [
            'gold-valley-gun-club' => [
                'name' => 'Gold Valley Gun Club',
                'province' => Province::Mpumalanga,
                'town' => 'Nelspruit',
                'notes' => 'Gold Valley Gun Club and Mountain Venue, Claremont Road.',
            ],
            'wattlespring-sport-shooting-club' => [
                'name' => 'Wattlespring Sport Shooting Club',
                'province' => Province::Gauteng,
                'town' => 'Pretoria',
                'notes' => 'Farm Onbekend, Bapsfontein / Bronkhorstspruit. Listed by CTSASA under Pretoria, Central Gauteng.',
            ],
            'maccauw-clay-target-club' => [
                'name' => 'Maccauw Clay Target Club',
                'province' => Province::FreeState,
                'town' => 'Bloemfontein',
            ],
            'centurion-gun-club' => [
                'name' => 'Centurion Gun Club',
                'province' => Province::Gauteng,
                'town' => 'Pretoria',
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
     * Upcoming domestic CTSASA championships from the official 2026 calendar
     * (https://ctsasa.co.za/competition-calendar/, version 24/06/26) as of 2026-09-14.
     * Past 2026 dates and overseas ISSF/NSSA/FITASC worlds are omitted.
     * Entry URLs from https://ctsasa.co.za/competition-entry-forms-2026/ where a form is live.
     *
     * @return list<array<string, mixed>>
     */
    private function matches(): array
    {
        return [
            [
                'slug' => 'ctsasa-sa-national-english-sporting-2026',
                'title' => 'SA National English Sporting & FITASC Trap1 Championships',
                'venue' => 'gold-valley-gun-club',
                'starts_at' => '2026-09-19 08:00:00',
                'ends_at' => '2026-09-20 17:00:00',
                'level' => EventLevel::National,
                'status' => EventStatus::EntriesOpen,
                'disciplines' => ['sporting-clays', 'trap'],
                'entry_url' => 'https://forms.gle/wZUH6G5Efhz2aXBr9',
                'description' => 'CTSASA national English Sporting and FITASC Trap1 championships at Gold Valley Gun Club, Nelspruit. Entries via CTSASA Google Form.',
            ],
            [
                'slug' => 'ctsasa-olympic-trial-wattlespring-2026',
                'title' => 'Olympic Trial (Trap and Skeet)',
                'venue' => 'wattlespring-sport-shooting-club',
                'starts_at' => '2026-09-26 08:00:00',
                'ends_at' => '2026-09-27 17:00:00',
                'level' => EventLevel::National,
                'status' => EventStatus::EntriesOpen,
                'disciplines' => ['trap', 'skeet'],
                'entry_url' => 'https://forms.gle/h3CAdUttq9rmADrS9',
                'description' => 'Additional Olympic trial: trap and skeet, 2 × 100 targets of each discipline, at Wattlespring Sport Shooting Club, Pretoria. Entries via CTSASA Google Form.',
            ],
            [
                'slug' => 'ctsasa-fs-fitasc-sporting-2026',
                'title' => 'Free State FITASC Sporting Championship',
                'venue' => 'maccauw-clay-target-club',
                'starts_at' => '2026-10-03 08:00:00',
                'ends_at' => '2026-10-04 17:00:00',
                'level' => EventLevel::Provincial,
                'status' => EventStatus::EntriesOpen,
                'disciplines' => ['sporting-clays'],
                'entry_url' => 'https://forms.gle/ETHz87Bfm6RCdDCv8',
                'description' => 'CTSASA Free State FITASC Sporting championship at Maccauw Clay Target Club, Bloemfontein. Entries via CTSASA Google Form.',
            ],
            [
                'slug' => 'ctsasa-gn-english-sporting-trap1-2026',
                'title' => 'Gauteng North English Sporting & FITASC Trap1 Championships',
                'venue' => 'centurion-gun-club',
                'starts_at' => '2026-10-10 08:00:00',
                'ends_at' => '2026-10-11 17:00:00',
                'level' => EventLevel::Provincial,
                'status' => EventStatus::EntriesOpen,
                'disciplines' => ['sporting-clays', 'trap'],
                'entry_url' => 'https://forms.gle/BXAtBffat75TYw657',
                'description' => 'CTSASA Gauteng North English Sporting and FITASC Trap1 championships at Centurion Gun Club, Pretoria. Entries via CTSASA Google Form.',
            ],
            [
                'slug' => 'ctsasa-nc-standard-2026',
                'title' => 'Northern Cape Standard Championships',
                'venue' => 'maccauw-clay-target-club',
                'starts_at' => '2026-10-31 08:00:00',
                'ends_at' => '2026-11-01 17:00:00',
                'level' => EventLevel::Provincial,
                'status' => EventStatus::EntriesOpen,
                'disciplines' => ['trap', 'skeet'],
                'entry_url' => 'https://docs.google.com/forms/d/e/1FAIpQLSeI8XW06B048ZyWlO4cfxdeFEkbB-LlBEKiQvc42HtuBhU6Mw/viewform',
                'description' => 'CTSASA Northern Cape Standard championships, hosted by Maccauw Clay Target Club in Bloemfontein. Entries via CTSASA Google Form.',
            ],
            [
                'slug' => 'ctsasa-chairmans-cup-2026',
                'title' => "Chairman's Cup (Inter-Provincial Championship)",
                'venue' => 'wattlespring-sport-shooting-club',
                'starts_at' => '2026-11-21 08:00:00',
                'ends_at' => '2026-11-22 17:00:00',
                'level' => EventLevel::National,
                'status' => EventStatus::Confirmed,
                'disciplines' => ['sporting-clays', 'trap', 'skeet'],
                'entry_url' => 'https://ctsasa.co.za/competition-calendar/',
                'description' => "CTSASA Chairman's Cup inter-provincial championship, hosted by Central Gauteng at Wattlespring Sport Shooting Club. No entry form published yet.",
            ],
        ];
    }

    private function storeLogo(): ?string
    {
        $source = database_path('seeders/data/ctsasa-logo.png');

        if (! is_file($source)) {
            return Organisation::query()->where('slug', 'ctsasa')->value('logo_path');
        }

        $path = 'organisation-logos/ctsasa.png';
        Storage::disk('media')->put($path, file_get_contents($source));

        return $path;
    }
}
