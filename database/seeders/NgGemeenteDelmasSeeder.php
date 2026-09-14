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
                'name' => 'Delmas Gongskiet',
                'province' => Province::Mpumalanga,
                'town' => 'Delmas',
                'max_distance_m' => 450,
                'notes' => '2027 poster lists 200–450 m. Farm name not printed.',
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
                'status' => EventStatus::Confirmed,
                'confirmed_at' => now(),
                'capacity' => 60,
                'entry_url' => null,
                'description' => 'Saturday 30 January 2027 in Delmas. Four-person teams, entries limited to 60 teams. Distances 200–450 m. Prize money on the poster: teams R40 000 / R30 000 / R20 000, individuals R5 000 / R3 000, first lady R5 000. No entry fee, farm or contact printed on this poster.',
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
