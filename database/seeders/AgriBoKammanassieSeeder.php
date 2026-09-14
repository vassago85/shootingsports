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

class AgriBoKammanassieSeeder extends Seeder
{
    public function run(): void
    {
        $discipline = Discipline::query()->where('slug', 'gong-shooting')->firstOrFail();

        $host = Organisation::query()->updateOrCreate(
            ['slug' => 'agri-bo-kammanassie'],
            [
                'name' => 'Agri Bo-Kammanassie',
                'short_name' => 'Bo-Kammanassie',
                'type' => OrganisationType::Association,
                'province' => Province::WesternCape,
                'town' => 'Uniondale',
                'email' => 'gert@dutoit.com',
                'phone' => '082 400 4155',
                'description' => 'Agricultural association in the Kammanassie / Langkloof. Hosts veldskiet and gong days.',
                'accredited' => false,
                'visitors_welcome' => true,
                'status' => ListingStatus::Published,
                'verification_state' => VerificationState::Unconfirmed,
                'source' => ListingSource::Staff,
            ],
        );

        $host->syncDisciplines([$discipline->id], $discipline->id);

        $venue = Venue::query()->updateOrCreate(
            ['slug' => 'meulrivier-dutoit'],
            [
                'name' => 'Meulrivier',
                'province' => Province::WesternCape,
                'town' => 'Langkloof',
                'lat' => -33.7749722,
                'lng' => 22.8269583,
                'notes' => 'Du Toit, R62 Langkloof. About 70 km from George and 40 km from Uniondale. Poster coordinates 33°46\'29.90"S 22°49\'37.05"E.',
                'access' => VenueAccess::GuestByArrangement,
                'status' => ListingStatus::Published,
                'verification_state' => VerificationState::Unconfirmed,
                'source' => ListingSource::Staff,
            ],
        );

        $event = Event::query()->updateOrCreate(
            ['slug' => 'agri-bo-kammanassie-2-man-gong-2026'],
            [
                'title' => '2-Man Gong Challenge',
                'host_organisation_id' => $host->id,
                'venue_id' => $venue->id,
                'starts_at' => '2026-10-10 09:00:00',
                'ends_at' => null,
                'all_day' => true,
                'level' => EventLevel::Club,
                'status' => EventStatus::EntriesOpen,
                'confirmed_at' => now(),
                'entry_fee_cents' => 80000,
                'round_count' => 30,
                'entry_url' => null,
                'description' => 'Saturday 10 October 2026 at Meulrivier (Du Toit), R62 Langkloof. Veldskiet, 30 rounds, R800 per team of two. Registration and sighting from 07:00, compulsory briefing 08:30, shooting from 09:00. Entries close 3 October 2026. Contact Gert 082 400 4155 or gert@dutoit.com. Food stalls on site.',
                'banner_path' => $this->storeBanner(),
                'source' => ListingSource::Staff,
                'last_verified_at' => now(),
            ],
        );

        $event->syncDisciplines([$discipline->id], $discipline->id);
    }

    private function storeBanner(): ?string
    {
        $filename = 'agri-bo-kammanassie-2-man-gong-2026.png';
        $source = database_path('seeders/data/'.$filename);

        if (! is_file($source)) {
            return Event::query()->where('slug', 'agri-bo-kammanassie-2-man-gong-2026')->value('banner_path');
        }

        $path = 'event-banners/'.$filename;
        Storage::disk('media')->put($path, file_get_contents($source));

        return $path;
    }
}
