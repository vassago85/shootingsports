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

class NrlHunterEventsSeeder extends Seeder
{
    public function run(): void
    {
        $discipline = Discipline::query()->where('slug', 'nrl-hunter')->firstOrFail();

        $host = Organisation::query()->updateOrCreate(
            ['slug' => 'nrl-hunter-sa'],
            [
                'name' => 'NRL Hunter South Africa',
                'short_name' => 'NRLH SA',
                'type' => OrganisationType::Association,
                'province' => null,
                'town' => null,
                'website_url' => 'https://www.nrlhuntersa.org',
                'description' => 'NRL Hunter season matches in South Africa — hunting-position precision rifle, scored on PractiScore.',
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
                    'level' => EventLevel::Series,
                    'status' => $match['status'],
                    'confirmed_at' => now(),
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
            'kleine-weide' => [
                'name' => 'Kleine Weide',
                'province' => Province::EasternCape,
                'town' => 'Somerset East',
                'notes' => 'Listed by NRL Hunter SA as Kleine Weide, EC. Season 6 poster places The Highlands Hunter in Somerset East.',
            ],
            'elandsberg' => [
                'name' => 'Elandsberg',
                'province' => Province::NorthernCape,
                'town' => 'Strydenburg',
                'notes' => 'Listed by NRL Hunter SA as Elandsberg, NC. Season 6 poster places Die Ribbok Jagter at Elandsberg, Strydenburg.',
            ],
            'cintsa' => [
                'name' => 'Cintsa',
                'province' => Province::EasternCape,
                'town' => 'East London',
                'notes' => 'Listed by NRL Hunter SA as Cintsa, EC. Season 6 poster places Frontier Face-Off at Cintsa, East London.',
            ],
            'palmietfontein' => [
                'name' => 'Palmietfontein',
                'province' => Province::NorthWest,
                'town' => 'Wolmaransstad',
                'notes' => 'Listed by NRL Hunter SA as Palmietfontein, NW. Season 6 poster places Farm Flair Wild West at Wolmaransstad. Contact on the poster: Tiaan 071 382 7993.',
            ],
            'kwa-thabileng-lodge' => [
                'name' => 'Kwa Thabileng Lodge',
                'province' => Province::FreeState,
                'town' => 'Reitz',
                'notes' => 'Listed by NRL Hunter SA as Reitz, FS. Season 6 poster brands the venue Kwa Thabileng River Lodge.',
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
                    'notes' => $attributes['notes'],
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
     * Season 6 cards from https://www.nrlhuntersa.org/index.php/2027-season-matches
     * as staff type them in. Dates come from the match posters, not the HTML.
     *
     * @return list<array<string, mixed>>
     */
    private function matches(): array
    {
        return [
            [
                'slug' => 'nrl-hunter-highlands-hunter-2027',
                'title' => 'The Highlands Hunter',
                'venue' => 'kleine-weide',
                'starts_at' => '2027-01-30 08:00:00',
                'ends_at' => null,
                'status' => EventStatus::EntriesOpen,
                'entry_url' => 'https://practiscore.com/nrl-hunter-the-highlands-hunter-clone/register',
                'banner' => 'nrl-hunter-highlands-hunter-2027.png',
                'description' => 'NRL Hunter Season 6 match at Kleine Weide, Somerset East, Eastern Cape. Entries via PractiScore.',
            ],
            [
                'slug' => 'nrl-hunter-die-ribbok-jagter-2026',
                'title' => 'Die Ribbok Jagter',
                'venue' => 'elandsberg',
                'starts_at' => '2026-10-03 08:00:00',
                'ends_at' => null,
                'status' => EventStatus::EntriesOpen,
                'entry_url' => 'https://practiscore.com/nrl-hunter-die-ribbok-jagter-s6/register',
                'banner' => 'nrl-hunter-die-ribbok-jagter-2026.png',
                'description' => 'NRL Hunter Season 6 match at Elandsberg, Strydenburg, Northern Cape. Entries via PractiScore.',
            ],
            [
                'slug' => 'nrl-hunter-frontier-faceoff-2027',
                'title' => 'Frontier Face-Off',
                'venue' => 'cintsa',
                'starts_at' => '2027-04-03 08:00:00',
                'ends_at' => null,
                'status' => EventStatus::EntriesOpen,
                'entry_url' => 'https://practiscore.com/nrl-hunter-frontier-faceoff-s6/register',
                'banner' => 'nrl-hunter-frontier-faceoff-2027.png',
                'description' => 'NRL Hunter Season 6 match at Cintsa, East London, Eastern Cape. Entries via PractiScore.',
            ],
            [
                'slug' => 'nrl-hunter-farm-flair-wild-west-2026',
                'title' => 'Farm Flair Wild West',
                'venue' => 'palmietfontein',
                'starts_at' => '2026-11-21 08:00:00',
                'ends_at' => null,
                'status' => EventStatus::EntriesOpen,
                'entry_url' => 'https://practiscore.com/nrl-hunter-farm-flair-wild-west/register',
                'banner' => 'nrl-hunter-farm-flair-wild-west-2026.png',
                'description' => 'NRL Hunter Season 6 match at Palmietfontein, Wolmaransstad, North West. Entries via PractiScore.',
            ],
            [
                'slug' => 'nrl-hunter-kwa-thabileng-2027',
                'title' => 'Kwa Thabileng Lodge',
                'venue' => 'kwa-thabileng-lodge',
                'starts_at' => '2027-03-13 08:00:00',
                'ends_at' => null,
                'status' => EventStatus::EntriesOpen,
                'entry_url' => 'https://practiscore.com/nrl-hunter-kwathabileng-hunter/register',
                'banner' => 'nrl-hunter-kwa-thabileng-2027.png',
                'description' => 'NRL Hunter Season 6 match at Kwa Thabileng Lodge, Reitz, Free State. Entries via PractiScore.',
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
