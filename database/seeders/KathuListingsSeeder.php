<?php

namespace Database\Seeders;

use App\Enums\EventKind;
use App\Enums\EventLevel;
use App\Enums\EventStatus;
use App\Enums\ListingSource;
use App\Enums\ListingStatus;
use App\Enums\OrganisationType;
use App\Enums\ProviderCategory;
use App\Enums\ProviderTier;
use App\Enums\Province;
use App\Enums\VenueAccess;
use App\Enums\VerificationState;
use App\Models\Event;
use App\Models\Organisation;
use App\Models\Provider;
use App\Models\Venue;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Storage;

/**
 * Kathu Mine Shooting Club, the Boomsticks branches published on
 * boomsticks.co.za/contact/, and the 21 November 2026 demo day
 * posted by @boomsticks_south_africa. Paarl is already listed.
 * Impact Proshop and VGK Firearms appear on that page under their own names.
 */
class KathuListingsSeeder extends Seeder
{
    public function run(): void
    {
        $club = $this->club();
        $range = $this->range();

        foreach ($this->boomsticksBranches() as $branch) {
            $provider = Provider::withTrashed()->updateOrCreate(
                ['slug' => $branch['slug']],
                $branch,
            );

            if ($provider->trashed()) {
                $provider->restore();
            }
        }

        $this->demoDay($club, $range);
    }

    private function club(): Organisation
    {
        return Organisation::query()->updateOrCreate(
            ['slug' => 'kathu-mine-shooting-club'],
            [
                'name' => 'Kathu Mine Shooting Club',
                'short_name' => 'Sishenmyn Skietbaan',
                'type' => OrganisationType::Club,
                'province' => Province::NorthernCape,
                'town' => 'Kathu',
                'website_url' => 'https://kalaharicc.co.za/kathu-mine-shooting-club/',
                'description' => 'Also known as the Sishen Mine Shooting Club and Sishenmyn Skietbaan. Outdoor club just outside Kathu. The field is 900 m, with 12 covered shooting tables and permanent gongs from 200 m to 700 m. Joining fee R200. Membership is R960 a year, or R480 a year for a senior, junior, or full-time student.',
                'private_contact' => 'Application form: jacques.esterhuyzen@angloamerican.com, published on the Kalahari Country Club page.',
                'accredited' => false,
                'visitors_welcome' => true,
                'status' => ListingStatus::Published,
                'verification_state' => VerificationState::Unconfirmed,
                'source' => ListingSource::Staff,
                'last_verified_at' => now(),
            ],
        );
    }

    private function range(): Venue
    {
        return Venue::query()->updateOrCreate(
            ['slug' => 'sishenmyn-skietbaan'],
            [
                'name' => 'Sishenmyn Skietbaan',
                'province' => Province::NorthernCape,
                'town' => 'Kathu',
                'max_distance_m' => 900,
                'website_url' => 'https://kalaharicc.co.za/kathu-mine-shooting-club/',
                'description' => 'Kathu Mine Shooting Club range just outside Kathu, also called the Sishen Mine Shooting Range. A 900 m field with 12 covered shooting tables and permanent gongs from 200 m to 700 m.',
                'access' => VenueAccess::Public,
                'status' => ListingStatus::Published,
                'verification_state' => VerificationState::Unconfirmed,
                'source' => ListingSource::Staff,
                'last_verified_at' => now(),
            ],
        );
    }

    private function demoDay(Organisation $club, Venue $range): void
    {
        $event = Event::query()->updateOrCreate(
            ['slug' => 'boomsticks-demo-day-sishenmyn-2026-11-21'],
            [
                'title' => 'Boomsticks Demo Day',
                'host_organisation_id' => $club->id,
                'venue_id' => $range->id,
                'starts_at' => '2026-11-21 00:00:00',
                'ends_at' => null,
                'all_day' => true,
                'level' => EventLevel::Club,
                'kind' => EventKind::Training,
                'status' => EventStatus::EntriesOpen,
                'confirmed_at' => now(),
                'entry_fee_cents' => 20000,
                'entry_url' => 'https://boomsticks.co.za/category/promotions/',
                'website_url' => 'https://boomsticks.co.za/events/',
                'instagram_url' => 'https://www.instagram.com/boomsticks_south_africa/',
                'banner_path' => $this->storeBanner(),
                'description' => 'Boomsticks and Normark demo day at the Sishen Mine Shooting Range, Sishenmyn Skietbaan, Kathu, on 21 November 2026. Beretta, Tikka, Sako and Stoeger. Entry is R200 per person and includes ammunition and refreshments. Prizes are offered.',
                'source' => ListingSource::Staff,
                'last_verified_at' => now(),
            ],
        );

        $kathu = Provider::query()->where('slug', 'boomsticks-kathu')->first();

        if ($kathu !== null) {
            $event->partners()->sync([
                $kathu->id => ['sort_order' => 0],
            ]);
        }
    }

    private function storeBanner(): ?string
    {
        $filename = 'boomsticks-demo-day-sishenmyn-2026-11-21.png';
        $source = database_path('seeders/data/'.$filename);

        if (! is_file($source)) {
            return Event::query()->where('slug', 'boomsticks-demo-day-sishenmyn-2026-11-21')->value('banner_path');
        }

        $path = 'event-banners/'.$filename;
        Storage::disk('media')->put($path, (string) file_get_contents($source));

        return $path;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function boomsticksBranches(): array
    {
        $shared = [
            'category' => ProviderCategory::Dealer,
            'website_url' => 'https://boomsticks.co.za',
            'tier' => ProviderTier::Free,
            'status' => ListingStatus::Published,
            'verification_state' => VerificationState::Unconfirmed,
            'source' => ListingSource::Staff,
            'last_verified_at' => now(),
        ];

        return [
            array_merge($shared, [
                'slug' => 'boomsticks-kathu',
                'name' => 'Boomsticks Kathu',
                'services' => [
                    ProviderCategory::Gunsmith->value,
                    ProviderCategory::ReloadingComponents->value,
                ],
                'province' => Province::NorthernCape,
                'town' => 'Kathu',
                'phone' => '053 723 3581',
                'tagline' => 'Gunsmithing, parts, reloading components, and shooting accessories in Kathu.',
                'description' => 'Boomsticks shop at 2 Bokmakierie Street, Kathu, 8446. Gunsmithing and parts, reloading components, shooting accessories, and clothing.',
            ]),
            array_merge($shared, [
                'slug' => 'boomsticks-graaff-reinet',
                'name' => 'Boomsticks Graaff Reinet',
                'services' => [],
                'province' => Province::EasternCape,
                'town' => 'Graaff Reinet',
                'phone' => '049 891 1265',
                'tagline' => 'Boomsticks shop in Graaff Reinet.',
                'description' => 'Boomsticks shop at 65 Church Street, Graaff Reinet, 6280.',
            ]),
            array_merge($shared, [
                'slug' => 'boomsticks-upington',
                'name' => 'Boomsticks Upington',
                'services' => [],
                'province' => Province::NorthernCape,
                'town' => 'Upington',
                'phone' => '054 331 1780',
                'tagline' => 'Boomsticks shop in Upington.',
                'description' => 'Boomsticks shop at 69 Schroder Street, Upington, 8801.',
            ]),
            array_merge($shared, [
                'slug' => 'boomsticks-somerset-east',
                'name' => 'Boomsticks Somerset East',
                'services' => [],
                'province' => Province::EasternCape,
                'town' => 'Somerset East',
                'phone' => '076 580 2391',
                'tagline' => 'Boomsticks shop in Somerset East.',
                'description' => 'Boomsticks shop at 16 Charles Street, Somerset East, 5850.',
            ]),
        ];
    }
}
