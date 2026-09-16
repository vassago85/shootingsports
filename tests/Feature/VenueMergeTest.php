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
