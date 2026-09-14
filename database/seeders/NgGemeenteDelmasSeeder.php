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

class NgGemeenteDelmasSeeder extends Seeder
{
    public function run(): void
    {
        $discipline = Discipline::query()->where('slug', 'gong-shooting')->firstOrFail();

        $host = Organisation::query()->updateOrCreate(
            ['slug' => 'ng-gemeente-delmas'],
            [
                'name' => 'NG Gemeente Delmas',
                'short_name' => 'NG Delmas',
                'type' => OrganisationType::Club,
                'province' => Province::Mpumalanga,
                'town' => 'Delmas',
                'email' => 'william@gunshopdelmas.co.za',
                'description' => 'Dutch Reformed congregation in Delmas. Hosts the annual gemeente gongskiet.',
                'accredited' => false,
                'visitors_welcome' => true,
                'status' => ListingStatus::Published,
                'verification_state' => VerificationState::Unconfirmed,
                'source' => ListingSource::Staff,
            ],
        );

        $host->syncDisciplines([$discipline->id], $discipline->id);

        $venue = Venue::query()->updateOrCreate(
            ['slug' => 'delmas-gongskiet'],
            [
                'name' => 'Plaas Witklipbank',
                'province' => Province::Mpumalanga,
                'town' => 'Delmas',
                'max_distance_m' => 450,
                'notes' => 'NG Gemeente Delmas Gongskiet venue. Eight lanes, four gongs per lane, 200–450 m. Power gong on the day.',
                'access' => VenueAccess::GuestByArrangement,
                'status' => ListingStatus::Published,
                'verification_state' => VerificationState::Unconfirmed,
                'source' => ListingSource::Staff,
            ],
        );

        $event = Event::query()->updateOrCreate(
            ['slug' => 'ng-delmas-gongskiet-2027'],
            [
                'title' => 'NG Gemeente Delmas Gongskiet',
                'host_organisation_id' => $host->id,
                'venue_id' => $venue->id,
                'starts_at' => '2027-01-30 08:00:00',
                'ends_at' => null,
                'all_day' => true,
                'level' => EventLevel::Club,
                'status' => EventStatus::EntriesOpen,
                'confirmed_at' => now(),
                'capacity' => 60,
                'entry_fee_cents' => 75000,
                'round_count' => 32,
                'stage_count' => 8,
                'entry_url' => null,
                'description' => 'Saturday 30 January 2027 at Plaas Witklipbank, Delmas. Four-person teams, entries limited to 60 teams. Eight lanes, four gongs per lane, 32 shots minimum, 200–450 m. R750 per shooter (R3 000 per team) including coffee, rusks, lunch and dinner. Power gong R100 per chance. Prize money on the poster: teams R40 000 / R30 000 / R20 000, individuals R5 000 / R3 000, first lady R5 000. Entries close 4 December 2026. Email the entry form and proof of payment to william@gunshopdelmas.co.za — no place is held until both arrive. EFT to NG Moedergemeente Delmas, reference team name and gongskiet.',
                'banner_path' => $this->storeBanner(),
                'source' => ListingSource::Staff,
                'last_verified_at' => now(),
            ],
        );

        $event->syncDisciplines([$discipline->id], $discipline->id);
    }

    private function storeBanner(): ?string
    {
        $filename = 'ng-delmas-gongskiet-2027.png';
        $source = database_path('seeders/data/'.$filename);

        if (! is_file($source)) {
            return Event::query()->where('slug', 'ng-delmas-gongskiet-2027')->value('banner_path');
        }

        $path = 'event-banners/'.$filename;
        Storage::disk('media')->put($path, file_get_contents($source));

        return $path;
    }
}
