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

class SahuntersVaaldriehoekSeeder extends Seeder
{
    public function run(): void
    {
        $discipline = Discipline::query()->where('slug', 'hunting-rifle')->firstOrFail();

        $host = Organisation::query()->updateOrCreate(
            ['slug' => 'sahunters-vaaldriehoek'],
            [
                'name' => 'SA Jagters Vaaldriehoek',
                'short_name' => 'Vaaldriehoek',
                'type' => OrganisationType::Club,
                'province' => Province::FreeState,
                'town' => 'Parys',
                'website_url' => 'https://sahunters.co.za',
                'description' => 'SA Jagters / SA Hunters Vaal Triangle branch.',
                'accredited' => false,
                'visitors_welcome' => true,
                'status' => ListingStatus::Published,
                'verification_state' => VerificationState::Unconfirmed,
                'source' => ListingSource::Staff,
            ],
        );

        $host->syncDisciplines([$discipline->id], $discipline->id);

        $venue = Venue::query()->updateOrCreate(
            ['slug' => 'koepel-parys'],
            [
                'name' => 'Koepel',
                'province' => Province::FreeState,
                'town' => 'Parys',
                'notes' => 'Farm about 20 km out of Parys, in Koepel. Named on the 22 August 2026 Gevaarlike Wild poster (page 1 of 3).',
                'access' => VenueAccess::GuestByArrangement,
                'status' => ListingStatus::Published,
                'verification_state' => VerificationState::Unconfirmed,
                'source' => ListingSource::Staff,
            ],
        );

        $event = Event::query()->updateOrCreate(
            ['slug' => 'vaaldriehoek-gevaarlike-wild-2026'],
            [
                'title' => 'Gevaarlike Wild Skiet',
                'host_organisation_id' => $host->id,
                'venue_id' => $venue->id,
                'starts_at' => '2026-08-22 08:00:00',
                'ends_at' => null,
                'all_day' => true,
                'level' => EventLevel::Club,
                'status' => EventStatus::Completed,
                'confirmed_at' => now(),
                'entry_fee_cents' => 80000,
                'stage_count' => 5,
                'round_count' => 20,
                'entry_url' => null,
                'description' => 'Saturday 22 August 2026. Five lanes (lion, hippo, crocodile, elephant, sport), four shots per lane. Classes .300–.360, 9.3/.375–.416, and .458 and larger. R800 per shooter including lamb braai. Farm about 20 km out of Parys, in Koepel. This poster is page 1 of 3 and has no contact number.',
                'banner_path' => $this->storeBanner(),
                'source' => ListingSource::Staff,
                'last_verified_at' => now(),
            ],
        );

        $event->syncDisciplines([$discipline->id], $discipline->id);
    }

    private function storeBanner(): ?string
    {
        $filename = 'vaaldriehoek-gevaarlike-wild-2026.png';
        $source = database_path('seeders/data/'.$filename);

        if (! is_file($source)) {
            return Event::query()->where('slug', 'vaaldriehoek-gevaarlike-wild-2026')->value('banner_path');
        }

        $path = 'event-banners/'.$filename;
        Storage::disk('media')->put($path, file_get_contents($source));

        return $path;
    }
}
