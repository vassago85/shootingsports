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

class FreeCivilianShootersSeeder extends Seeder
{
    public function run(): void
    {
        $discipline = Discipline::query()->where('slug', 'pr22-rimfire')->firstOrFail();

        $host = Organisation::query()->updateOrCreate(
            ['slug' => 'free-civilian-shooters'],
            [
                'name' => 'Free Civilian Shooters',
                'short_name' => 'FCS',
                'type' => OrganisationType::Series,
                'province' => Province::Gauteng,
                'town' => 'Randfontein',
                'phone' => '073 007 5364',
                'description' => 'Gauteng .22 Rimfire Long Rifle Challenge. Positional rimfire at known metric distances.',
                'accredited' => false,
                'visitors_welcome' => true,
                'status' => ListingStatus::Published,
                'verification_state' => VerificationState::Unconfirmed,
                'source' => ListingSource::Staff,
            ],
        );

        $host->syncDisciplines([$discipline->id], $discipline->id);

        $venue = Venue::query()->updateOrCreate(
            ['slug' => 'dwarskloof-shooting-range'],
            [
                'name' => 'Dwarskloof Shooting Range',
                'province' => Province::Gauteng,
                'town' => 'Randfontein',
                'max_distance_m' => 100,
                'notes' => 'Riaan 076 250 6565. Hosts the Free Civilian Shooters .22 long-rifle challenge.',
                'access' => VenueAccess::GuestByArrangement,
                'status' => ListingStatus::Published,
                'verification_state' => VerificationState::Unconfirmed,
                'source' => ListingSource::Staff,
            ],
        );

        $event = Event::query()->updateOrCreate(
            ['slug' => 'free-civilian-round-3-2026'],
            [
                'title' => 'Round 3 — .22 Rimfire Long Rifle',
                'host_organisation_id' => $host->id,
                'venue_id' => $venue->id,
                'starts_at' => '2026-10-17 08:00:00',
                'ends_at' => null,
                'all_day' => true,
                'level' => EventLevel::Series,
                'status' => EventStatus::EntriesOpen,
                'confirmed_at' => now(),
                'entry_fee_cents' => 20000,
                'round_count' => 50,
                'stage_count' => 6,
                'entry_url' => null,
                'description' => 'Saturday 17 October 2026 at Dwarskloof Shooting Range, Randfontein. Gauteng .22 Rimfire Long Rifle Challenge, Round 3. R200 per person, 50 rounds. Three stages at 30 m, one each at 50 m, 75 m and 100 m. Positions may include prone, sitting, bench, barricades, standing or a rope. Registration from 08:00. RSVP by 16 October 2026 at 19:00. Match director Jacoco 073 007 5364. Range Riaan 076 250 6565. Catering by Fresh Folio.',
                'banner_path' => $this->storeBanner(),
                'source' => ListingSource::Staff,
                'last_verified_at' => now(),
            ],
        );

        $event->syncDisciplines([$discipline->id], $discipline->id);
    }

    private function storeBanner(): ?string
    {
        $filename = 'free-civilian-round-3-2026.png';
        $source = database_path('seeders/data/'.$filename);

        if (! is_file($source)) {
            return Event::query()->where('slug', 'free-civilian-round-3-2026')->value('banner_path');
        }

        $path = 'event-banners/'.$filename;
        Storage::disk('media')->put($path, file_get_contents($source));

        return $path;
    }
}
