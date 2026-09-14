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

class SahrsaEventsSeeder extends Seeder
{
    public function run(): void
    {
        $discipline = Discipline::query()->where('slug', 'hunting-rifle')->firstOrFail();

        $host = Organisation::query()->updateOrCreate(
            ['slug' => 'sahrsa'],
            [
                'name' => 'SA Hunting Rifle Shooting Association',
                'short_name' => 'SAHRSA',
                'type' => OrganisationType::Association,
                'province' => null,
                'town' => null,
                'email' => 'info.cwhrsa@gmail.com',
                'phone' => '082 224 5258',
                'website_url' => 'https://www.sahuntingrifle.co.za',
                'description' => 'National hunting-rifle league. Provincial opens and the SA Open, scored as official SAHRSA events.',
                'accredited' => false,
                'visitors_welcome' => true,
                'status' => ListingStatus::Published,
                'verification_state' => VerificationState::Unconfirmed,
                'source' => ListingSource::Staff,
            ],
        );

        $host->syncDisciplines([$discipline->id], $discipline->id);

        $venues = $this->venues();

        foreach ($this->matches() as $match) {
            $venue = $venues[$match['venue']];

            $event = Event::query()->updateOrCreate(
                ['slug' => $match['slug']],
                [
                    'title' => $match['title'],
                    'host_organisation_id' => $host->id,
                    'venue_id' => $venue->id,
                    'starts_at' => $match['starts_at'],
                    'ends_at' => $match['ends_at'],
                    'all_day' => true,
                    'level' => EventLevel::Provincial,
                    'status' => $match['status'],
                    'confirmed_at' => now(),
                    'entry_fee_cents' => $match['entry_fee_cents'],
                    'member_fee_cents' => $match['member_fee_cents'],
                    'entry_url' => $match['entry_url'],
                    'description' => $match['description'],
                    'banner_path' => $this->storeBanner($match['slug'], $match['banner'] ?? null),
                    'source' => ListingSource::Staff,
                    'last_verified_at' => now(),
                ],
            );

            $event->syncDisciplines([$discipline->id], $discipline->id);
        }
    }

    /**
     * @return array<string, Venue>
     */
    private function venues(): array
    {
        $rows = [
            'kinga-distillery' => [
                'name' => 'Kinga Distillery',
                'province' => Province::WesternCape,
                'town' => 'Montagu',
                'lat' => -33.8299333,
                'lng' => 20.2607306,
                'notes' => 'Talana Farm, Montagu. Poster coordinates 33°49\'47.76"S 20°15\'38.63"E.',
            ],
        ];

        $venues = [];

        foreach ($rows as $slug => $attributes) {
            $venues[$slug] = Venue::query()->updateOrCreate(
                ['slug' => $slug],
                [
                    'name' => $attributes['name'],
                    'province' => $attributes['province'],
                    'town' => $attributes['town'],
                    'lat' => $attributes['lat'] ?? null,
                    'lng' => $attributes['lng'] ?? null,
                    'notes' => $attributes['notes'] ?? null,
                    'access' => VenueAccess::GuestByArrangement,
                    'status' => ListingStatus::Published,
                    'verification_state' => VerificationState::Unconfirmed,
                    'source' => ListingSource::Staff,
                ],
            );
        }

        return $venues;
    }

    /**
     * Official SAHRSA league matches typed from posters / sahuntingrifle.co.za.
     *
     * @return list<array<string, mixed>>
     */
    private function matches(): array
    {
        return [
            [
                'slug' => 'sahrsa-cape-winelands-open-2026',
                'title' => 'Cape Winelands Open',
                'venue' => 'kinga-distillery',
                'starts_at' => '2026-10-03 09:00:00',
                'ends_at' => null,
                'status' => EventStatus::EntriesOpen,
                'entry_fee_cents' => 60000,
                'member_fee_cents' => 55000,
                'entry_url' => 'https://www.sahuntingrifle.co.za',
                'banner' => 'sahrsa-cape-winelands-open-2026.png',
                'description' => 'Safari Outdoor Boland Open / Cape Winelands Hunting Rifle. Saturday 3 October 2026 at Kinga Distillery, Talana Farm, Montagu. Official SAHRSA league event. Members R550, non-members R600, juniors and penkoppe R450, spitbraai lunch included. Registration 07:00–08:30, briefing 08:30, shooting from 09:00. Entries close 30 September 2026 on sahuntingrifle.co.za. Contact Kobus Visser 082 224 5258 or info.cwhrsa@gmail.com.',
            ],
        ];
    }

    private function storeBanner(string $slug, ?string $filename): ?string
    {
        if (! filled($filename)) {
            return Event::query()->where('slug', $slug)->value('banner_path');
        }

        $source = database_path('seeders/data/'.$filename);

        if (! is_file($source)) {
            return Event::query()->where('slug', $slug)->value('banner_path');
        }

        $path = 'event-banners/'.$filename;
        Storage::disk('media')->put($path, file_get_contents($source));

        return $path;
    }
}
