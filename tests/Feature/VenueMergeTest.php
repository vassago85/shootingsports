<?php

use App\Enums\GeocodeSource;
use App\Enums\ListingStatus;
use App\Enums\Province;
use App\Models\Event;
use App\Models\Organisation;
use App\Models\Venue;
use App\Services\Venues\VenueDuplicateFinder;
use App\Services\Venues\VenueMerger;

beforeEach(function () {
    $this->withoutVite();
});

it('merges duplicate venues into primary and archives losers', function () {
    $host = Organisation::factory()->create(['status' => ListingStatus::Published]);

    $primary = Venue::factory()->create([
        'name' => 'Legends Adventure Farm',
        'town' => 'Pretoria',
        'province' => Province::Gauteng,
        'address' => null,
        'lat' => -25.74,
        'lng' => 28.53,
        'geocode_source' => GeocodeSource::Staff,
        'status' => ListingStatus::Published,
    ]);

    $dup = Venue::factory()->create([
        'name' => 'Papaberg / Legends Adventure Farm',
        'town' => 'Pretoria',
        'province' => Province::Gauteng,
        'address' => 'Farm Rd',
        'lat' => -25.93,
        'lng' => 28.08,
        'geocode_source' => GeocodeSource::Nominatim,
        'status' => ListingStatus::Published,
    ]);

    $event = Event::factory()->confirmed()->create([
        'host_organisation_id' => $host->id,
        'venue_id' => $dup->id,
        'starts_at' => now()->addDays(10),
    ]);

    $result = app(VenueMerger::class)->merge($primary, [$dup]);

    expect($result['events'])->toBe(1)
        ->and($result['archived'])->toBe(1);

    $event->refresh();
    $primary->refresh();
    $dup->refresh();

    expect($event->venue_id)->toBe($primary->id)
        ->and($dup->status)->toBe(ListingStatus::Archived)
        ->and($primary->address)->toBe('Farm Rd')
        ->and((float) $primary->lat)->toEqual(-25.74)
        ->and($primary->geocode_source)->toBe(GeocodeSource::Staff);
});

it('keeps the duplicate photos and profile when the listed range has none', function () {
    $primary = Venue::factory()->create([
        'slug' => 'listed-range',
        'name' => 'Listed Range',
        'description' => null,
        'website_url' => null,
        'image_paths' => null,
        'logo_path' => null,
        'max_distance_m' => null,
        'facilities' => [],
        'status' => ListingStatus::Published,
    ]);

    $duplicate = Venue::factory()->create([
        'slug' => 'listed-range-duplicate',
        'name' => 'Listed Range Duplicate',
        'description' => 'On the farm Onbekend.',
        'website_url' => 'https://wattlespring.co.za',
        'image_paths' => ['range-images/wattlespring-restaurant.png'],
        'logo_path' => 'range-logos/wattlespring-logo.png',
        'max_distance_m' => 100,
        'facilities' => ['100 m rifle range'],
        'status' => ListingStatus::Published,
    ]);

    app(VenueMerger::class)->merge($primary, [$duplicate]);

    $primary->refresh();
    $duplicate->refresh();

    expect($primary->description)->toBe('On the farm Onbekend.')
        ->and($primary->website_url)->toBe('https://wattlespring.co.za')
        ->and($primary->image_paths)->toBe(['range-images/wattlespring-restaurant.png'])
        ->and($primary->logo_path)->toBe('range-logos/wattlespring-logo.png')
        ->and($primary->max_distance_m)->toBe(100)
        ->and($primary->facilities)->toBe(['100 m rifle range'])
        ->and($duplicate->status)->toBe(ListingStatus::Archived);

    $this->get('/ranges/listed-range-duplicate')
        ->assertRedirect(route('ranges.show', 'listed-range'));
});

it('adopts loser pin when primary has none', function () {
    $primary = Venue::factory()->create([
        'lat' => null,
        'lng' => null,
        'geocode_source' => null,
        'status' => ListingStatus::Published,
    ]);

    $dup = Venue::factory()->create([
        'lat' => -26.1,
        'lng' => 28.1,
        'geocode_source' => GeocodeSource::Nominatim,
        'status' => ListingStatus::Published,
    ]);

    app(VenueMerger::class)->merge($primary, [$dup]);

    $primary->refresh();

    expect((float) $primary->lat)->toEqual(-26.1)
        ->and($primary->geocode_source)->toBe(GeocodeSource::Nominatim);
});

it('finds likely duplicate venues by normalised name and town', function () {
    Venue::factory()->create([
        'name' => 'Centurion Gun Club',
        'town' => 'Pretoria',
        'province' => Province::Gauteng,
        'status' => ListingStatus::Published,
    ]);
    Venue::factory()->create([
        'name' => 'Centurion Club',
        'town' => 'Pretoria',
        'province' => Province::Gauteng,
        'status' => ListingStatus::Published,
    ]);
    Venue::factory()->create([
        'name' => 'Unrelated Range',
        'town' => 'Cape Town',
        'province' => Province::WesternCape,
        'status' => ListingStatus::Published,
    ]);

    $groups = app(VenueDuplicateFinder::class)->groups();

    expect($groups)->not->toBeEmpty();

    $flat = collect($groups)->flatMap(fn (array $g) => $g['venues']->pluck('name'));

    expect($flat->contains('Centurion Gun Club'))->toBeTrue()
        ->and($flat->contains('Centurion Club'))->toBeTrue();
});
