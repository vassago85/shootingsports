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

class SpringbokvlakteGongskietSeeder extends Seeder
{
    public function run(): void
    {
        $discipline = Discipline::query()->where('slug', 'gong-shooting')->firstOrFail();

        $host = Organisation::query()->updateOrCreate(
            ['slug' => 'springbokvlakte-gong-skiet'],
            [
                'name' => 'Springbokvlakte Gong Skiet',
                'short_name' => 'Springbokvlakte',
                'type' => OrganisationType::Series,
                'province' => Province::Limpopo,
                'town' => 'Mookgophong',
                'phone' => '082 828 9734',
                'description' => 'Gongskiet on the Springbok Flats. Mik, skiet, geniet.',
                'accredited' => false,
                'visitors_welcome' => true,
                'status' => ListingStatus::Published,
                'verification_state' => VerificationState::Unconfirmed,
                'source' => ListingSource::Staff,
            ],
        );

        $host->syncDisciplines([$discipline->id], $discipline->id);

        $venue = Venue::query()->updateOrCreate(
            ['slug' => 'plaas-middeldoorn'],
            [
                'name' => 'Plaas Middeldoorn',
                'province' => Province::Limpopo,
                'town' => 'Mookgophong',
                'max_distance_m' => 450,
                'notes' => 'Springbokvlakte Gongskiet venue. Contact 082 828 9734. Snacks and cash bar on the poster.',
                'access' => VenueAccess::GuestByArrangement,
                'status' => ListingStatus::Published,
                'verification_state' => VerificationState::Unconfirmed,
                'source' => ListingSource::Staff,
            ],
        );

        $event = Event::query()->updateOrCreate(
            ['slug' => 'springbokvlakte-gongskiet-2026'],
            [
                'title' => 'Gongskiet',
                'host_organisation_id' => $host->id,
                'venue_id' => $venue->id,
                'starts_at' => '2026-10-31 08:00:00',
                'ends_at' => null,
                'all_day' => true,
                'level' => EventLevel::Series,
                'status' => EventStatus::EntriesOpen,
                'confirmed_at' => now(),
                'entry_fee_cents' => 300000,
                'entry_url' => null,
                'description' => 'Saturday 31 October 2026 at Plaas Middeldoorn, Mookgophong, Limpopo. R3 000 per team of 4 (coffee, lunch and prizes). Six gong lanes with five gongs, maximum about 450 m, maximum calibre .30. Check-in from 06:00, opening 07:30, shooting from 08:00. Entries close 21 October 2026. Contact 082 828 9734. Prize money on the poster: R15 000 / R10 000 / R5 000 / R3 000 / R2 000 plus other prizes.',
                'banner_path' => $this->storeBanner(),
                'source' => ListingSource::Staff,
                'last_verified_at' => now(),
            ],
        );

        $event->syncDisciplines([$discipline->id], $discipline->id);
    }

    private function storeBanner(): ?string
    {
        $filename = 'springbokvlakte-gongskiet-2026.png';
        $source = database_path('seeders/data/'.$filename);

        if (! is_file($source)) {
            return Event::query()->where('slug', 'springbokvlakte-gongskiet-2026')->value('banner_path');
        }

        $path = 'event-banners/'.$filename;
        Storage::disk('media')->put($path, file_get_contents($source));

        return $path;
    }
}
