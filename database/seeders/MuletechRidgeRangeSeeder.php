<?php

namespace Database\Seeders;

use App\Enums\EventLevel;
use App\Enums\EventStatus;
use App\Enums\ListingSource;
use App\Enums\ListingStatus;
use App\Enums\Province;
use App\Enums\VenueAccess;
use App\Enums\VerificationState;
use App\Models\Discipline;
use App\Models\Event;
use App\Models\Venue;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Storage;

class MuletechRidgeRangeSeeder extends Seeder
{
    public function run(): void
    {
        $discipline = Discipline::query()->where('slug', 'precision-rifle')->firstOrFail();

        // Muletech Ridge Range is a Venue, not a Club. An earlier
        // version of this seeder created a matching Organisation row
        // typed `club`, which then polluted the /clubs directory. The
        // ghost was removed in the 2026_09_15 reclassification
        // migration; the seeder now only creates the Venue and lets
        // the event stand hostless — the range operator is the host.
        $venue = Venue::query()->updateOrCreate(
            ['slug' => 'muletech-ridge-range'],
            [
                'name' => 'Muletech Ridge Range',
                'province' => Province::FreeState,
                'town' => 'Bothaville',
                'max_distance_m' => 900,
                'notes' => 'Book on 073 551 6065. 150–900 m on the 14 November 2026 fundraiser poster.',
                'access' => VenueAccess::GuestByArrangement,
                'status' => ListingStatus::Published,
                'verification_state' => VerificationState::Unconfirmed,
                'source' => ListingSource::Staff,
            ],
        );

        $venue->syncDisciplines([$discipline->id], $discipline->id);

        $event = Event::query()->updateOrCreate(
            ['slug' => 'muletech-loskuil-fundraising-shoot-2026'],
            [
                'title' => 'Fundraising Shooting Day',
                'host_organisation_id' => null,
                'venue_id' => $venue->id,
                'starts_at' => '2026-11-14 08:00:00',
                'ends_at' => null,
                'all_day' => true,
                'level' => EventLevel::Club,
                'status' => EventStatus::EntriesOpen,
                'confirmed_at' => now(),
                'entry_fee_cents' => 45000,
                'round_count' => 50,
                'stage_count' => 7,
                'entry_url' => null,
                'description' => 'Saturday 14 November 2026 at Muletech Ridge Range, Bothaville. Fundraiser for Loskuil Primary School. Seven stages including one timed stage, 150–900 m, at least 50 rounds. R450 per person. Bonus gong R500 for five shots. Lucky draw. Book on 073 551 6065.',
                'banner_path' => $this->storeBanner(),
                'source' => ListingSource::Staff,
                'last_verified_at' => now(),
            ],
        );

        $event->syncDisciplines([$discipline->id], $discipline->id);
    }

    private function storeBanner(): ?string
    {
        $filename = 'muletech-loskuil-fundraising-shoot-2026.png';
        $source = database_path('seeders/data/'.$filename);

        if (! is_file($source)) {
            return Event::query()->where('slug', 'muletech-loskuil-fundraising-shoot-2026')->value('banner_path');
        }

        $path = 'event-banners/'.$filename;
        Storage::disk('media')->put($path, file_get_contents($source));

        return $path;
    }
}
