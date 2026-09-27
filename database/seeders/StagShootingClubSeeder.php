<?php

namespace Database\Seeders;

use App\Enums\EventKind;
use App\Enums\EventLevel;
use App\Enums\EventStatus;
use App\Enums\GautengMetro;
use App\Enums\GeocodeSource;
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

/**
 * West Rand Stag Shooting Club, from the club flyer (September 2026).
 *
 * This is not Secpro's STAG range at Kleinplaas, Makhanda. The flyer
 * places the club at Cecil Payne Range and names it a NARFO West Rand
 * brand ambassador. No public website was found for this club.
 */
class StagShootingClubSeeder extends Seeder
{
    public function run(): void
    {
        $discipline = Discipline::query()->where('slug', 'action-defensive')->firstOrFail();

        $club = Organisation::query()->updateOrCreate(
            ['slug' => 'stag-shooting-club'],
            [
                'name' => 'Stag Shooting Club',
                'short_name' => 'Stag',
                'type' => OrganisationType::Club,
                'province' => Province::Gauteng,
                'town' => 'Roodepoort',
                'phone' => '083 292 1436',
                'description' => 'Scenario-based defensive shooting on the West Rand. Paper, steel and scenario stations, with drills for home invasion, armed robbery, vehicle hijacking and close-quarter threats. Open to any lawful firearm owner. NARFO West Rand brand ambassadors. Annual membership R1,500. Shoot on the first Sunday of every month at Cecil Payne Range. Text Russell 083 292 1436, Andre 083 635 2251 or Chanté 078 623 7440.',
                'accredited' => false,
                'visitors_welcome' => true,
                'status' => ListingStatus::Published,
                'verification_state' => VerificationState::Unconfirmed,
                'source' => ListingSource::Staff,
                'last_verified_at' => now(),
            ],
        );

        $club->syncDisciplines([$discipline->id], $discipline->id);

        $venue = Venue::query()->updateOrCreate(
            ['slug' => 'cecil-payne-shooting-range'],
            [
                'name' => 'Cecil Payne Shooting Range',
                'province' => Province::Gauteng,
                'town' => 'Roodepoort',
                'metro' => GautengMetro::Johannesburg,
                'address' => '18 Nadine Street, Florida, Roodepoort, 1710',
                'lat' => -26.1859444,
                'lng' => 27.9314722,
                'geocode_source' => GeocodeSource::Staff,
                'geocoded_at' => now(),
                'notes' => 'Behind the Cecil Payne Sporting Complex. Also listed as the JMPD range. Range office 011 674 0157. Coordinates published by the Gauteng Pin Shooting Association: S 26°11\'09.4" E 27°55\'53.3".',
                'access' => VenueAccess::GuestByArrangement,
                'status' => ListingStatus::Published,
                'verification_state' => VerificationState::Unconfirmed,
                'source' => ListingSource::Staff,
                'last_verified_at' => now(),
            ],
        );

        $venue->syncDisciplines([$discipline->id], $discipline->id);

        $banner = $this->storeBanner();

        foreach ($this->sessions() as $session) {
            $event = Event::query()->updateOrCreate(
                ['slug' => $session['slug']],
                [
                    'title' => 'Monthly Defensive Shoot',
                    'host_organisation_id' => $club->id,
                    'venue_id' => $venue->id,
                    'starts_at' => $session['starts_at'],
                    'ends_at' => null,
                    'all_day' => true,
                    'level' => EventLevel::Club,
                    'kind' => EventKind::Training,
                    'status' => EventStatus::Confirmed,
                    'confirmed_at' => now(),
                    'entry_fee_cents' => 20000,
                    'member_fee_cents' => 15000,
                    'entry_url' => null,
                    'description' => 'First Sunday of the month at Cecil Payne Shooting Range, 18 Nadine Street, Florida, Roodepoort. Scenario-based defensive shooting: paper, steel and scenario stations. Drills include home invasion, armed robbery, vehicle hijacking and close-quarter threats. Open to any lawful firearm owner. Members pay R150 range fee. Non-members pay a R200 club fee plus the range fee. Annual membership is R1,500. No online entry — text Russell 083 292 1436, Andre 083 635 2251 or Chanté 078 623 7440.',
                    'banner_path' => $banner,
                    'source' => ListingSource::Staff,
                    'last_verified_at' => now(),
                ],
            );

            $event->syncDisciplines([$discipline->id], $discipline->id);
        }
    }

    /**
     * Standing first-Sunday sessions. The flyer does not name a start time.
     *
     * @return list<array{slug: string, starts_at: string}>
     */
    private function sessions(): array
    {
        return [
            ['slug' => 'stag-defensive-shoot-2026-10-04', 'starts_at' => '2026-10-04 00:00:00'],
            ['slug' => 'stag-defensive-shoot-2026-11-01', 'starts_at' => '2026-11-01 00:00:00'],
            ['slug' => 'stag-defensive-shoot-2026-12-06', 'starts_at' => '2026-12-06 00:00:00'],
            ['slug' => 'stag-defensive-shoot-2027-01-03', 'starts_at' => '2027-01-03 00:00:00'],
            ['slug' => 'stag-defensive-shoot-2027-02-07', 'starts_at' => '2027-02-07 00:00:00'],
            ['slug' => 'stag-defensive-shoot-2027-03-07', 'starts_at' => '2027-03-07 00:00:00'],
        ];
    }

    private function storeBanner(): ?string
    {
        $filename = 'stag-shooting-club-monthly.jpg';
        $source = database_path('seeders/data/'.$filename);
        $path = 'event-banners/'.$filename;

        if (! is_file($source)) {
            return Event::query()->where('slug', 'stag-defensive-shoot-2026-10-04')->value('banner_path');
        }

        Storage::disk('media')->put($path, file_get_contents($source));

        return $path;
    }
}
