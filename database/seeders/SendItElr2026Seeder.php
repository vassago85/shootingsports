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

class SendItElr2026Seeder extends Seeder
{
    public function run(): void
    {
        $elr = Discipline::query()->where('slug', 'elr')->firstOrFail();
        $logoPath = $this->storeLogo();

        $series = Organisation::query()->updateOrCreate(
            ['slug' => 'send-it-elr'],
            [
                'name' => 'Send It ELR Shooting',
                'short_name' => 'Send It',
                'type' => OrganisationType::Series,
                'province' => Province::WesternCape,
                'town' => 'Wellington',
                'website_url' => null,
                'description' => 'ELR series: 1-mile and 2-mile challenges, .22 ELR, and Send It Ultra. Hosted with APWY, 3% Tactical, PEKS, Matroosbergstasie, Lambert Brand and Taai Boskraal.',
                'logo_path' => $logoPath,
                'accredited' => false,
                'visitors_welcome' => true,
                'status' => ListingStatus::Published,
                'verification_state' => VerificationState::Unconfirmed,
                'source' => ListingSource::Staff,
            ],
        );

        $series->syncDisciplines([$elr->id], $elr->id);

        $venues = $this->venues();

        foreach ($this->matches() as $match) {
            $venue = $venues[$match['venue']];

            $event = Event::query()->updateOrCreate(
                ['slug' => $match['slug']],
                [
                    'title' => $match['title'],
                    'host_organisation_id' => $series->id,
                    'venue_id' => $venue->id,
                    'starts_at' => $match['starts_at'],
                    'ends_at' => $match['ends_at'],
                    'all_day' => true,
                    'level' => EventLevel::Series,
                    'status' => EventStatus::Confirmed,
                    'confirmed_at' => now(),
                    'description' => $match['description'],
                    'source' => ListingSource::Staff,
                    'last_verified_at' => now(),
                ],
            );

            $event->syncDisciplines([$elr->id], $elr->id);
        }
    }

    /**
     * @return array<string, Venue>
     */
    private function venues(): array
    {
        $rows = [
            'darling-steel-valley' => [
                'name' => 'Darling Steel Valley',
                'province' => Province::WesternCape,
                'town' => 'Darling',
                'notes' => '1-mile challenges hosted with APWY & 3% Tactical.',
            ],
            'peks-range-wellington' => [
                'name' => 'PEKS Range',
                'province' => Province::WesternCape,
                'town' => 'Wellington',
                'notes' => '.22 LR ELR.',
            ],
            'matroosbergstasie-skietklub' => [
                'name' => 'Matroosbergstasie Skietklub',
                'province' => Province::WesternCape,
                'town' => 'Matroosberg',
                'notes' => '2-mile challenges.',
            ],
            'hanover-northern-cape' => [
                'name' => 'Hanover ELR Range',
                'province' => Province::NorthernCape,
                'town' => 'Hanover',
                'notes' => 'Send It Ultra.',
            ],
            'taai-boskraal' => [
                'name' => 'Taai Boskraal',
                'province' => Province::WesternCape,
                'town' => 'Western Cape',
                'notes' => 'ELR match in Send It format, brought by Lambert Brand.',
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
                    'access' => VenueAccess::GuestByArrangement,
                    'notes' => $attributes['notes'],
                    'status' => ListingStatus::Published,
                    'verification_state' => VerificationState::Unconfirmed,
                    'source' => ListingSource::Staff,
                ],
            );
        }

        return $venues;
    }

    /**
     * @return list<array{slug: string, title: string, venue: string, starts_at: string, ends_at: ?string, description: string}>
     */
    private function matches(): array
    {
        return [
            [
                'slug' => 'send-it-1-mile-challenge-q1-2026',
                'title' => '1-Mile Challenge Q1',
                'venue' => 'darling-steel-valley',
                'starts_at' => '2026-02-07 08:00:00',
                'ends_at' => null,
                'description' => 'Hosted by APWY & 3% Tactical at Darling Steel Valley.',
            ],
            [
                'slug' => 'send-it-peks-22lr-elr-march-2026',
                'title' => 'PEKS Range .22 LR ELR',
                'venue' => 'peks-range-wellington',
                'starts_at' => '2026-03-07 08:00:00',
                'ends_at' => null,
                'description' => '.22 LR ELR at PEKS Range, Wellington.',
            ],
            [
                'slug' => 'send-it-2-mile-challenge-q1-2026',
                'title' => '2-Mile Challenge Q1',
                'venue' => 'matroosbergstasie-skietklub',
                'starts_at' => '2026-03-14 08:00:00',
                'ends_at' => null,
                'description' => '2-mile challenge at Matroosbergstasie Skietklub.',
            ],
            [
                'slug' => 'send-it-1-mile-challenge-q2-2026',
                'title' => '1-Mile Challenge Q2',
                'venue' => 'darling-steel-valley',
                'starts_at' => '2026-03-28 08:00:00',
                'ends_at' => null,
                'description' => 'Hosted by APWY & 3% Tactical at Darling Steel Valley.',
            ],
            [
                'slug' => 'send-it-2-mile-challenge-q2-2026',
                'title' => '2-Mile Challenge Q2',
                'venue' => 'matroosbergstasie-skietklub',
                'starts_at' => '2026-04-11 08:00:00',
                'ends_at' => null,
                'description' => '2-mile challenge at Matroosbergstasie Skietklub.',
            ],
            [
                'slug' => 'send-it-ultra-2026',
                'title' => 'Send It Ultra',
                'venue' => 'hanover-northern-cape',
                'starts_at' => '2026-04-30 08:00:00',
                'ends_at' => '2026-05-02 17:00:00',
                'description' => 'Send It Ultra at Hanover, Northern Cape.',
            ],
            [
                'slug' => 'send-it-2-mile-challenge-q3-2026',
                'title' => '2-Mile Challenge Q3',
                'venue' => 'matroosbergstasie-skietklub',
                'starts_at' => '2026-05-23 08:00:00',
                'ends_at' => null,
                'description' => '2-mile challenge at Matroosbergstasie Skietklub.',
            ],
            [
                'slug' => 'send-it-elr-match-july-2026',
                'title' => 'ELR Match (Send It format)',
                'venue' => 'taai-boskraal',
                'starts_at' => '2026-07-25 08:00:00',
                'ends_at' => null,
                'description' => 'Brought to you by Lambert Brand at Taai Boskraal.',
            ],
            [
                'slug' => 'send-it-peks-22lr-elr-september-2026',
                'title' => 'PEKS Range .22 LR ELR',
                'venue' => 'peks-range-wellington',
                'starts_at' => '2026-09-05 08:00:00',
                'ends_at' => null,
                'description' => '.22 LR ELR at PEKS Range, Wellington.',
            ],
            [
                'slug' => 'send-it-1-mile-challenge-q3-2026',
                'title' => '1-Mile Challenge Q3',
                'venue' => 'darling-steel-valley',
                'starts_at' => '2026-09-19 08:00:00',
                'ends_at' => null,
                'description' => 'Hosted by APWY & 3% Tactical at Darling Steel Valley.',
            ],
            [
                'slug' => 'send-it-1-mile-challenge-final-2026',
                'title' => '1-Mile Challenge Final',
                'venue' => 'darling-steel-valley',
                'starts_at' => '2026-10-31 08:00:00',
                'ends_at' => null,
                'description' => 'Hosted by APWY & 3% Tactical at Darling Steel Valley.',
            ],
            [
                'slug' => 'send-it-2-mile-challenge-final-2026',
                'title' => '2-Mile Challenge Final',
                'venue' => 'matroosbergstasie-skietklub',
                'starts_at' => '2026-11-14 08:00:00',
                'ends_at' => null,
                'description' => '2-mile challenge final at Matroosbergstasie Skietklub.',
            ],
        ];
    }

    private function storeLogo(): ?string
    {
        $source = database_path('seeders/data/send-it-elr-logo.png');

        if (! is_file($source)) {
            return Organisation::query()->where('slug', 'send-it-elr')->value('logo_path');
        }

        $path = 'organisation-logos/send-it-elr.png';
        Storage::disk('media')->put($path, file_get_contents($source));

        return $path;
    }
}
