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

class SahuntersDuikerSeeder extends Seeder
{
    public function run(): void
    {
        $discipline = Discipline::query()->where('slug', 'hunting-rifle')->firstOrFail();

        $host = Organisation::query()->updateOrCreate(
            ['slug' => 'sahunters-duiker'],
            [
                'name' => 'SA Hunters Duiker',
                'short_name' => 'Duiker',
                'type' => OrganisationType::Club,
                'province' => Province::Mpumalanga,
                'town' => 'Grootvlei',
                'phone' => '083 278 1739',
                'website_url' => 'https://sahunters.co.za',
                'description' => 'SA Jagters / SA Hunters Duiker branch. Branch shoots and fundraising days at Charles Kruger Skietbaan.',
                'accredited' => false,
                'visitors_welcome' => true,
                'status' => ListingStatus::Published,
                'verification_state' => VerificationState::Unconfirmed,
                'source' => ListingSource::Staff,
            ],
        );

        $host->syncDisciplines([$discipline->id], $discipline->id);

        $venue = Venue::query()->updateOrCreate(
            ['slug' => 'charles-kruger-skietbaan'],
            [
                'name' => 'Charles Kruger Skietbaan',
                'province' => Province::Mpumalanga,
                'town' => 'Grootvlei',
                'max_distance_m' => 400,
                'notes' => 'Host range for SA Hunters Duiker. Bottari Corolla Challenge is six shots in 60 seconds at 400 m.',
                'access' => VenueAccess::GuestByArrangement,
                'status' => ListingStatus::Published,
                'verification_state' => VerificationState::Unconfirmed,
                'source' => ListingSource::Staff,
            ],
        );

        $event = Event::query()->updateOrCreate(
            ['slug' => 'bottari-corolla-challenge-2026'],
            [
                'title' => 'Bottari Corolla Challenge',
                'host_organisation_id' => $host->id,
                'venue_id' => $venue->id,
                'starts_at' => '2026-09-26 08:00:00',
                'ends_at' => null,
                'all_day' => true,
                'level' => EventLevel::Club,
                'status' => EventStatus::EntriesOpen,
                'confirmed_at' => now(),
                'round_count' => 6,
                'stage_count' => 1,
                'entry_url' => null,
                'description' => 'Saturday 26 September 2026 at Charles Kruger Skietbaan, Grootvlei. SA Hunters Duiker branch. Six shots in 60 seconds at 400 m. Lucky draw R100 per ticket on the day; first prize is a Howa barrel and action. Sponsored by Nic Bottari Toyota and Savuti Arms. WhatsApp Henk 083 278 1739, Andre 082 551 2765, Frikkie 083 417 2550.',
                'banner_path' => $this->storeBanner(),
                'source' => ListingSource::Staff,
                'last_verified_at' => now(),
            ],
        );

        $event->syncDisciplines([$discipline->id], $discipline->id);
    }

    private function storeBanner(): ?string
    {
        $filename = 'bottari-corolla-challenge-2026.png';
        $source = database_path('seeders/data/'.$filename);

        if (! is_file($source)) {
            return Event::query()->where('slug', 'bottari-corolla-challenge-2026')->value('banner_path');
        }

        $path = 'event-banners/'.$filename;
        Storage::disk('media')->put($path, file_get_contents($source));

        return $path;
    }
}
