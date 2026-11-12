<?php

use App\Enums\OrganisationType;
use App\Enums\Province;
use App\Models\Event;
use App\Models\Organisation;
use App\Models\Provider;
use App\Models\Venue;
use Database\Seeders\KathuListingsSeeder;

it('lists the Kathu club, its range, and the other Boomsticks shops', function () {
    $this->seed(KathuListingsSeeder::class);
    $this->seed(KathuListingsSeeder::class);

    $club = Organisation::query()->where('slug', 'kathu-mine-shooting-club')->first();

    expect($club)->not->toBeNull()
        ->and($club->name)->toBe('Kathu Mine Shooting Club')
        ->and($club->short_name)->toBe('Sishenmyn Skietbaan')
        ->and($club->type)->toBe(OrganisationType::Club)
        ->and($club->province)->toBe(Province::NorthernCape)
        ->and($club->town)->toBe('Kathu')
        ->and($club->accredited)->toBeFalse()
        ->and($club->visitors_welcome)->toBeTrue()
        ->and(Organisation::query()->where('slug', 'kathu-mine-shooting-club')->count())->toBe(1);

    $range = Venue::query()->where('slug', 'sishenmyn-skietbaan')->first();

    expect($range)->not->toBeNull()
        ->and($range->max_distance_m)->toBe(900)
        ->and($range->town)->toBe('Kathu')
        ->and(Venue::query()->where('slug', 'sishenmyn-skietbaan')->count())->toBe(1);

    expect(Provider::query()->whereIn('slug', [
        'boomsticks-kathu',
        'boomsticks-graaff-reinet',
        'boomsticks-upington',
        'boomsticks-somerset-east',
    ])->count())->toBe(4);

    $kathu = Provider::query()->where('slug', 'boomsticks-kathu')->first();

    expect($kathu->phone)->toBe('053 723 3581')
        ->and($kathu->town)->toBe('Kathu')
        ->and($kathu->services)->toBe(['gunsmith', 'reloading_components']);

    $this->get(route('clubs.index', ['q' => 'Sishenmyn']))
        ->assertOk()
        ->assertSee('Kathu Mine Shooting Club');

    $this->get(route('clubs.show', 'kathu-mine-shooting-club'))
        ->assertOk()
        ->assertSee('kalaharicc.co.za')
        ->assertSee('900 m')
        ->assertDontSee('jacques.esterhuyzen@angloamerican.com');

    $this->get(route('ranges.show', 'sishenmyn-skietbaan'))
        ->assertOk()
        ->assertSee('Sishenmyn Skietbaan')
        ->assertSee('900 m');

    $this->get(route('suppliers.index', ['q' => 'Kathu']))
        ->assertOk()
        ->assertSee('Boomsticks Kathu')
        ->assertDontSee('Impact Proshop');

    $this->get(route('suppliers.show', 'boomsticks-kathu'))
        ->assertOk()
        ->assertSee('2 Bokmakierie Street');

    $this->get(route('suppliers.show', 'boomsticks'))
        ->assertOk()
        ->assertSee('21 Station Street')
        ->assertDontSee('Bokmakierie');

    $demo = Event::query()->where('slug', 'boomsticks-demo-day-sishenmyn-2026-11-21')->first();

    expect($demo)->not->toBeNull()
        ->and($demo->all_day)->toBeTrue()
        ->and($demo->entry_fee_cents)->toBe(20000)
        ->and($demo->entry_url)->toBe('https://boomsticks.co.za/category/promotions/')
        ->and($demo->website_url)->toBe('https://boomsticks.co.za/events/')
        ->and($demo->starts_at->toDateString())->toBe('2026-11-21')
        ->and($demo->host_organisation_id)->toBe($club->id)
        ->and($demo->venue_id)->toBe($range->id)
        ->and($demo->banner_path)->toBe('event-banners/boomsticks-demo-day-sishenmyn-2026-11-21.png')
        ->and($demo->partners->pluck('slug')->all())->toBe(['boomsticks-kathu'])
        ->and(Event::query()->where('slug', 'boomsticks-demo-day-sishenmyn-2026-11-21')->count())->toBe(1);

    $this->get(route('events.show', 'boomsticks-demo-day-sishenmyn-2026-11-21'))
        ->assertOk()
        ->assertSee('Boomsticks Demo Day')
        ->assertSee('Normark')
        ->assertSee('Enter here')
        ->assertSee('boomsticks.co.za', false)
        ->assertSee('/media/event-banners/boomsticks-demo-day-sishenmyn-2026-11-21.png', false)
        ->assertSee('Beretta')
        ->assertSee('R200')
        ->assertSee('Boomsticks Kathu');
});
