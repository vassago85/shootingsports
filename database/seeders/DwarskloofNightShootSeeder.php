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

class DwarskloofNightShootSeeder extends Seeder
{
    public function run(): void
    {
        $discipline = Discipline::query()->where('slug', 'ipsc-practical')->firstOrFail();

        // Dwarskloof Shooting Range is a Venue, not a Club. An earlier
        // version of this seeder created a matching Organisation row
        // typed `club`, which then polluted the /clubs directory. The
        // ghost was removed in the 2026_09_15 reclassification
        // migration; the seeder now only creates the Venue and lets
        // the event stand hostless — the range operator is the host.
        $venue = Venue::query()->updateOrCreate(
            ['slug' => 'dwarskloof-shooting-range'],
            [
                'name' => 'Dwarskloof Shooting Range',
                'province' => Province::Gauteng,
                'town' => 'Randfontein',
                'notes' => 'Riaan 076 250 6565.',
                'access' => VenueAccess::GuestByArrangement,
                'status' => ListingStatus::Published,
                'verification_state' => VerificationState::Unconfirmed,
                'source' => ListingSource::Staff,
            ],
        );

        $venue->syncDisciplines([$discipline->id], $discipline->id);

        $event = Event::query()->updateOrCreate(
            ['slug' => 'dwarskloof-running-gunning-night-2026'],
            [
                'title' => 'Running & Gunning Fun Night Shoot',
                'host_organisation_id' => null,
                'venue_id' => $venue->id,
                'starts_at' => '2026-09-12 18:00:00',
                'ends_at' => null,
                'all_day' => false,
                'level' => EventLevel::Club,
                'status' => EventStatus::Completed,
                'confirmed_at' => now(),
                'entry_fee_cents' => 20000,
                'round_count' => 50,
                'entry_url' => null,
                'description' => 'Saturday 12 September 2026 at Dwarskloof Skietbaan. Briefing 18:00, shooting from 19:00. R200 range fee. 50 pistol-calibre rounds. Handguns and PCC welcome. Flashlight required, gun-mounted or handheld. Bring holsters, magazine pouches, and eye and ear protection.',
                'banner_path' => $this->storeBanner(),
                'source' => ListingSource::Staff,
                'last_verified_at' => now(),
            ],
        );

        $event->syncDisciplines([$discipline->id], $discipline->id);
    }

    private function storeBanner(): ?string
    {
        $filename = 'dwarskloof-running-gunning-night-2026.png';
        $source = database_path('seeders/data/'.$filename);

        if (! is_file($source)) {
            return Event::query()->where('slug', 'dwarskloof-running-gunning-night-2026')->value('banner_path');
        }

        $path = 'event-banners/'.$filename;
        Storage::disk('media')->put($path, file_get_contents($source));

        return $path;
    }
}
