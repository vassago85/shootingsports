<?php

use App\Enums\GeocodeSource;
use App\Enums\ListingStatus;
use App\Enums\Province;
use App\Models\Event;
use App\Models\Organisation;
use App\Models\Venue;
use App\Queries\PublicEventQuery;
use App\Services\Geocoding\VenueGeocoder;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    $this->withoutVite();
});

it('VenueGeocoder fills lat/lng from Nominatim and marks source', function () {
    Http::fake([
        'nominatim.openstreetmap.org/search*' => Http::response([
            [
                'lat' => '-25.860121',
                'lon' => '28.189029',
                'display_name' => 'Vektor Shooting Club, Centurion, Gauteng, South Africa',
            ],
        ], 200),
    ]);

    $venue = Venue::factory()->create([
        'name' => 'Vektor Shooting Club',
        'town' => 'Centurion',
        'province' => Province::Gauteng,
        'lat' => null,
        'lng' => null,
        'geocode_source' => null,
        'status' => ListingStatus::Published,
    ]);

    expect(app(VenueGeocoder::class)->fill($venue))->toBeTrue();

    $venue->refresh();

    expect($venue->lat)->not->toBeNull()
        ->and((float) $venue->lat)->toEqual(-25.860121)
        ->and((float) $venue->lng)->toEqual(28.189029)
        ->and($venue->geocode_source)->toBe(GeocodeSource::Nominatim)
        ->and($venue->geocoded_at)->not->toBeNull();
});

it('VenueGeocoder never overwrites a staff pin', function () {
    Http::fake();

    $venue = Venue::factory()->create([
        'lat' => -26.1,
        'lng' => 28.1,
        'geocode_source' => GeocodeSource::Staff,
        'status' => ListingStatus::Published,
    ]);

    expect(app(VenueGeocoder::class)->fill($venue, force: true))->toBeFalse();

    $venue->refresh();
    expect((float) $venue->lat)->toEqual(-26.1)
        ->and($venue->geocode_source)->toBe(GeocodeSource::Staff);

    Http::assertNothingSent();
});

it('PublicEventQuery radius filters by real venue distance from lat/lng', function () {
    $host = Organisation::factory()->create(['status' => ListingStatus::Published]);
    $near = Venue::factory()->create([
        'status' => ListingStatus::Published,
        'lat' => -25.75,
        'lng' => 28.23,
    ]);
    $far = Venue::factory()->create([
        'status' => ListingStatus::Published,
        'lat' => -33.92,
        'lng' => 18.42,
    ]);

    $nearEvent = Event::factory()->confirmed()->create([
        'host_organisation_id' => $host->id,
        'venue_id' => $near->id,
        'starts_at' => now()->addDays(5),
    ]);
    $farEvent = Event::factory()->confirmed()->create([
        'host_organisation_id' => $host->id,
        'venue_id' => $far->id,
        'starts_at' => now()->addDays(5),
    ]);

    $query = new PublicEventQuery(
        radiusKm: 100,
        nearLat: -25.75,
        nearLng: 28.23,
    );

    $ids = $query->get()->pluck('id')->all();

    expect($ids)->toContain($nearEvent->id)
        ->and($ids)->not->toContain($farEvent->id);
});

it('venues:geocode command geocodes missing pins', function () {
    Http::fake([
        'nominatim.openstreetmap.org/search*' => Http::response([
            ['lat' => '-26.2041', 'lon' => '28.0473', 'display_name' => 'Johannesburg'],
        ], 200),
    ]);

    Venue::factory()->create([
        'name' => 'Jozi Range',
        'town' => 'Johannesburg',
        'province' => Province::Gauteng,
        'lat' => null,
        'lng' => null,
        'status' => ListingStatus::Published,
    ]);

    $this->artisan('venues:geocode', ['--sleep' => 0, '--limit' => 5])
        ->assertSuccessful();

    expect(Venue::query()->where('name', 'Jozi Range')->first())
        ->geocode_source->toBe(GeocodeSource::Nominatim)
        ->lat->not->toBeNull();
});

it('VenueGeocoder falls back to town when the club name is unknown to OSM', function () {
    Http::fake([
        'nominatim.openstreetmap.org/search*' => Http::sequence()
            ->push([], 200) // club name unknown
            ->push([
                [
                    'lat' => '-25.836389',
                    'lon' => '28.180278',
                    'display_name' => 'Centurion, Gauteng, South Africa',
                ],
            ], 200), // town fallback
    ]);

    $venue = Venue::factory()->create([
        'name' => 'Centurion Gun Club',
        'town' => 'Centurion',
        'province' => Province::Gauteng,
        'address' => null,
        'lat' => null,
        'lng' => null,
        'geocode_source' => null,
        'status' => ListingStatus::Published,
    ]);

    expect(app(VenueGeocoder::class)->fill($venue))->toBeTrue();

    $venue->refresh();

    expect((float) $venue->lat)->toEqual(-25.836389)
        ->and($venue->geocode_source)->toBe(GeocodeSource::Nominatim);
});
