<?php

namespace Database\Seeders;

use App\Enums\EventLevel;
use App\Enums\EventStatus;
use App\Enums\GautengMetro;
use App\Enums\ListingSource;
use App\Enums\ListingStatus;
use App\Enums\OrganisationType;
use App\Enums\ProviderCategory;
use App\Enums\Province;
use App\Enums\VenueAccess;
use App\Enums\VerificationState;
use App\Models\Discipline;
use App\Models\Event;
use App\Models\Flag;
use App\Models\Organisation;
use App\Models\Provider;
use App\Models\Venue;
use Illuminate\Database\Seeder;

class DemoSeeder extends Seeder
{
    public function run(): void
    {
        $this->call(DatabaseSeeder::class);

        $prs = Discipline::query()->where('slug', 'prs')->firstOrFail();
        $pr22 = Discipline::query()->where('slug', 'pr22-rimfire')->firstOrFail();
        $fClass = Discipline::query()->where('slug', 'f-class')->firstOrFail();
        $ipsc = Discipline::query()->where('slug', 'ipsc-practical')->firstOrFail();

        $pprc = Organisation::query()->updateOrCreate(
            ['slug' => 'pretoria-precision-rifle-club'],
            [
                'name' => 'Pretoria Precision Rifle Club',
                'short_name' => 'PPRC',
                'type' => OrganisationType::Club,
                'province' => Province::Gauteng,
                'town' => 'Pretoria',
                'website_url' => 'https://pretoriaprc.co.za',
                'description' => 'Precision rifle club in Pretoria. Visitors welcome at most club matches.',
                'accredited' => true,
                'visitors_welcome' => true,
                'status' => ListingStatus::Published,
                'verification_state' => VerificationState::Verified,
                'last_verified_at' => now(),
                'source' => ListingSource::Staff,
            ],
        );
        $pprc->syncDisciplines([$prs->id, $pr22->id], $prs->id);

        $vaal = Organisation::query()->updateOrCreate(
            ['slug' => 'vaal-target-rifle-union'],
            [
                'name' => 'Vaal Target Rifle Union',
                'short_name' => 'VTRU',
                'type' => OrganisationType::Association,
                'province' => Province::Gauteng,
                'town' => 'Vereeniging',
                'visitors_welcome' => true,
                'status' => ListingStatus::Published,
                'verification_state' => VerificationState::Verified,
                'last_verified_at' => now()->subMonths(2),
                'source' => ListingSource::Staff,
            ],
        );
        $vaal->syncDisciplines([$fClass->id], $fClass->id);

        $range = Venue::query()->updateOrCreate(
            ['slug' => 'pprc-range-pretoria'],
            [
                'name' => 'PPRC Range',
                'province' => Province::Gauteng,
                'town' => 'Pretoria',
                'metro' => GautengMetro::Pretoria,
                'lat' => -25.7479000,
                'lng' => 28.2294000,
                'max_distance_m' => 800,
                'bay_count' => 12,
                'access' => VenueAccess::GuestByArrangement,
                'day_fee_cents' => 15000,
                'facilities' => ['toilets' => true, 'clubhouse' => true],
                'status' => ListingStatus::Published,
                'verification_state' => VerificationState::Verified,
                'last_verified_at' => now(),
                'source' => ListingSource::Staff,
            ],
        );
        $range->syncDisciplines([$prs->id, $pr22->id], $prs->id);

        $confirmed = Event::query()->updateOrCreate(
            ['slug' => 'pprc-prs-club-match-sep'],
            [
                'title' => 'PPRC PRS Club Match',
                'host_organisation_id' => $pprc->id,
                'venue_id' => $range->id,
                'starts_at' => now()->addWeeks(3)->setTime(8, 0),
                'all_day' => true,
                'level' => EventLevel::Club,
                'status' => EventStatus::EntriesOpen,
                'confirmed_at' => now(),
                'round_count' => 8,
                'entry_fee_cents' => 35000,
                'source' => ListingSource::Staff,
            ],
        );
        $confirmed->syncDisciplines([$prs->id], $prs->id);
        $confirmed->flags()->sync(
            Flag::query()->whereIn('slug', ['new-shooter-friendly', 'spectators-welcome'])->pluck('id'),
        );

        $planned = Event::query()->updateOrCreate(
            ['slug' => 'vaal-f-class-league-rd-4'],
            [
                'title' => 'Vaal F-Class League Rd 4',
                'host_organisation_id' => $vaal->id,
                'venue_id' => null,
                'starts_at' => now()->addMonths(2)->setTime(8, 0),
                'all_day' => true,
                'level' => EventLevel::Provincial,
                'status' => EventStatus::Planned,
                'source' => ListingSource::Staff,
            ],
        );
        $planned->syncDisciplines([$fClass->id], $fClass->id);

        $ipscClub = Organisation::query()->updateOrCreate(
            ['slug' => 'highveld-practical-shooters'],
            [
                'name' => 'Highveld Practical Shooters',
                'type' => OrganisationType::Club,
                'province' => Province::Gauteng,
                'town' => 'Johannesburg',
                'visitors_welcome' => true,
                'status' => ListingStatus::Published,
                'verification_state' => VerificationState::Ageing,
                'last_verified_at' => now()->subMonths(7),
                'source' => ListingSource::Staff,
            ],
        );
        $ipscClub->syncDisciplines([$ipsc->id], $ipsc->id);

        Event::query()->updateOrCreate(
            ['slug' => 'highveld-ipsc-level-1'],
            [
                'title' => 'Highveld IPSC Level 1',
                'host_organisation_id' => $ipscClub->id,
                'starts_at' => now()->addWeeks(5)->setTime(8, 0),
                'all_day' => true,
                'level' => EventLevel::Club,
                'status' => EventStatus::Confirmed,
                'confirmed_at' => now(),
                'stage_count' => 6,
                'source' => ListingSource::Staff,
            ],
        )->syncDisciplines([$ipsc->id], $ipsc->id);

        Provider::query()->updateOrCreate(
            ['slug' => 'ridgeline-rifleworks'],
            [
                'name' => 'Ridgeline Rifleworks',
                'category' => ProviderCategory::Gunsmith,
                'province' => Province::Gauteng,
                'town' => 'Pretoria',
                'metro' => GautengMetro::Pretoria,
                'description' => 'Precision rifle smithing and chassis work.',
                'status' => ListingStatus::Published,
                'verification_state' => VerificationState::Verified,
                'last_verified_at' => now(),
                'source' => ListingSource::Staff,
            ],
        );
    }
}
