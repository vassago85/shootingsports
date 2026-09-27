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
                    'round_count' => $match['round_count'] ?? null,
                    'stage_count' => $match['stage_count'] ?? null,
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
            'meerkatgat-skietbaan' => [
                'name' => 'Meerkatgat Skietbaan',
                'province' => Province::FreeState,
                'town' => 'Soutpan',
                'lat' => -28.6077778,
                'lng' => 26.0205556,
                'notes' => 'Plaas Daskop, R700, Soutpan district. 61 km north of Bloemfontein. Published coordinates 28°36\'28.0"S 26°01\'14.0"E.',
            ],
            'corsica-fees-terrein' => [
                'name' => 'Corsica Feesterrein',
                'province' => Province::NorthWest,
                'town' => 'Delareyville',
                'lat' => -26.7836667,
                'lng' => 25.2789167,
                'notes' => '20 km from Delareyville towards Vryburg. Published coordinates 26°47\'01.2"S 25°16\'44.1"E.',
            ],
            'jenkins-creek' => [
                'name' => 'Jenkins Creek',
                'province' => Province::EasternCape,
                'town' => 'Cradock',
                'lat' => -32.0720250,
                'lng' => 25.6156639,
                'notes' => 'Cradock district. Published coordinates 32°04\'19.29"S 25°36\'56.39"E.',
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
                'entry_url' => 'https://www.sahuntingrifle.co.za/events/314',
                'banner' => 'sahrsa-cape-winelands-open-2026.png',
                'description' => 'Safari Outdoor Boland Open / Cape Winelands Hunting Rifle. Saturday 3 October 2026 at Kinga Distillery, Talana Farm, Montagu. Official SAHRSA league event. Members R550, non-members R600, juniors and penkoppe R450, spitbraai lunch included. Registration 07:00–08:30, briefing 08:30, shooting from 09:00. Entries close 30 September 2026 on sahuntingrifle.co.za. Contact Kobus Visser 082 224 5258 or info.cwhrsa@gmail.com.',
            ],
            [
                'slug' => 'sahrsa-freestate-open-2026',
                'title' => 'Free State Open',
                'venue' => 'meerkatgat-skietbaan',
                'starts_at' => '2026-10-10 08:30:00',
                'ends_at' => null,
                'status' => EventStatus::EntriesOpen,
                'entry_fee_cents' => 45000,
                'member_fee_cents' => 40000,
                'round_count' => 30,
                'stage_count' => 6,
                'entry_url' => 'https://www.sahuntingrifle.co.za/events/317',
                'banner' => 'sahrsa-freestate-open-2026.png',
                'description' => 'Free State Mangaung Open. Official SAHRSA league event. Saturday 10 October 2026 at Meerkatgat Skietbaan, Plaas Daskop, Soutpan district, 61 km north of Bloemfontein on the R700. Six stages, 30 shots. Members R400, non-members R450. A steak is included with entry. Extra steaks R120. Sight-in 07:00–08:00, opening 08:00, shooting from 08:30. Chamber flags are compulsory. Entries on sahuntingrifle.co.za. Contact Ti-landré Pretorius 083 280 0044 or Daan Griesel 083 363 9955.',
            ],
            [
                'slug' => 'sahrsa-noordwes-open-2026',
                'title' => 'North West Open',
                'venue' => 'corsica-fees-terrein',
                'starts_at' => '2026-10-17 08:30:00',
                'ends_at' => null,
                'status' => EventStatus::EntriesOpen,
                'entry_fee_cents' => 50000,
                'member_fee_cents' => 45000,
                'round_count' => 30,
                'stage_count' => 6,
                'entry_url' => 'https://www.sahuntingrifle.co.za/events/315',
                'banner' => 'sahrsa-noordwes-open-2026.jpg',
                'description' => 'Safari Outdoor Noord-Wes Ope. Official SAHRSA league event. Saturday 17 October 2026 at Corsica Feesterrein, 20 km from Delareyville towards Vryburg. 30 rounds: three target stages and three gong stages. Members R450, juniors R400, non-members R500. Sight-in and registration from 07:00, compulsory briefing 08:15, shooting from 08:30. Entries on sahuntingrifle.co.za. Contact Stephan 083 273 6227 or Stian 071 332 7919.',
            ],
            [
                'slug' => 'sahrsa-cradock-22lr-pcp-2026',
                'title' => 'Cradock .22 / PCP',
                'venue' => 'jenkins-creek',
                'starts_at' => '2026-10-23 13:00:00',
                'ends_at' => null,
                'status' => EventStatus::EntriesOpen,
                'entry_fee_cents' => 35000,
                'member_fee_cents' => 30000,
                'entry_url' => 'https://www.sahuntingrifle.co.za/events/318',
                'banner' => 'sahrsa-cradock-22lr-2026.jpg',
                'description' => 'Cradock .22 / PCP veldskiet. Official SAHRSA league class. Friday 23 October 2026 at Jenkins Creek, Cradock district. Members R300, non-members R350. Sight-in from 07:00, opening 08:30, shooting from 13:00. Chamber flags are compulsory. Food at the venue. Enter this class separately from the .223 and the Open. Entries on sahuntingrifle.co.za.',
            ],
            [
                'slug' => 'sahrsa-cradock-223-2026',
                'title' => 'Cradock .223',
                'venue' => 'jenkins-creek',
                'starts_at' => '2026-10-23 08:30:00',
                'ends_at' => null,
                'status' => EventStatus::EntriesOpen,
                'entry_fee_cents' => 35000,
                'member_fee_cents' => 30000,
                'entry_url' => 'https://www.sahuntingrifle.co.za/events/319',
                'banner' => 'sahrsa-cradock-223-2026.jpg',
                'description' => 'Cradock .223 veldskiet. Official SAHRSA league class. Friday 23 October 2026 at Jenkins Creek, Cradock district. Members R300, non-members R350. Sight-in from 07:00. Opening 08:30, then the .223 shoot on Friday morning. Chamber flags are compulsory. Food at the venue. Enter this class separately from the .22 / PCP and the Open. Entries on sahuntingrifle.co.za.',
            ],
            [
                'slug' => 'sahrsa-cradock-open-2026',
                'title' => 'Cradock Open',
                'venue' => 'jenkins-creek',
                'starts_at' => '2026-10-24 09:00:00',
                'ends_at' => null,
                'status' => EventStatus::EntriesOpen,
                'entry_fee_cents' => 55000,
                'member_fee_cents' => 50000,
                'entry_url' => 'https://www.sahuntingrifle.co.za/events/320',
                'banner' => 'sahrsa-cradock-open-2026.jpg',
                'description' => 'Cradock Open veldskiet. Official SAHRSA league event. Saturday 24 October 2026 at Jenkins Creek, Cradock district. Members R500, non-members R550, juniors R400. Sight-in from 07:00. Opening 08:30, which is compulsory. The entry page lists shooting from 09:00. Chamber flags are compulsory. Food at the venue. Entries on sahuntingrifle.co.za.',
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
