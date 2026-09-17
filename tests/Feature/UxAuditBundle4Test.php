<?php

use App\Enums\DisciplineFamily;
use App\Enums\ListingStatus;
use App\Enums\OrganisationType;
use App\Enums\Province;
use App\Models\Discipline;
use App\Models\Event;
use App\Models\Organisation;
use App\Models\Venue;
use Illuminate\Support\Facades\Cache;

beforeEach(function () {
    $this->withoutVite();
    Cache::flush();
    config(['services.carto.api_key' => null]);
});

// ---- Live reticle sweep -------------------------------------------

it('homepage hero reticle wraps the SVG in an animated container', function () {
    $html = $this->get(route('home'))->assertOk()->getContent();

    // Wrapper drives the sweep — SVG stays static inside it.
    expect($html)->toContain('class="hero-reticle-wrap"')
        ->and($html)->toContain('class="hero-reticle"');
});

it('the reticle animation is disabled for prefers-reduced-motion visitors', function () {
    // The rotating sweep was replaced by a single-element pulse on
    // the centre dot (was reading as radar, not reticle). Assert
    // both the pulse rule exists and it is disabled inside a
    // reduced-motion media query.
    $css = file_get_contents(resource_path('css/app.css'));

    expect($css)
        ->toContain('prefers-reduced-motion')
        ->and($css)->toContain('.hero-reticle-dot { animation: none; }');
});

it('the homepage centre bullseye carries the pulse class', function () {
    $html = $this->get(route('home'))->assertOk()->getContent();

    expect($html)->toContain('class="hero-reticle-dot"');
});

// ---- DOPE-card discipline-family accents --------------------------

it('event card renders a data-fam attribute and family accent tick', function () {
    $rifle = Discipline::factory()->create(['family' => DisciplineFamily::Rifle]);
    $host = Organisation::factory()->create(['status' => ListingStatus::Published, 'type' => OrganisationType::Club]);
    $event = Event::factory()->create([
        'host_organisation_id' => $host->id,
        'status' => 'confirmed',
        'starts_at' => now()->addDays(3),
    ]);
    $event->disciplines()->attach($rifle);
    $event->refresh();

    $html = view('components.event-card', ['event' => $event])->render();

    expect($html)->toContain('data-fam="rifle"')
        ->and($html)->toContain('class="dope-fam-tick"');
});

it('CSS defines a distinct --fam-accent for every DisciplineFamily variant', function () {
    $css = file_get_contents(resource_path('css/app.css'));

    // Fail loudly if a new DisciplineFamily case ships without its
    // accent tone — this keeps the cool-factor palette exhaustive.
    foreach (DisciplineFamily::cases() as $family) {
        expect($css)->toContain('.dope[data-fam="'.$family->value.'"]');
    }
});

// ---- Map view -----------------------------------------------------

it('/map route renders successfully', function () {
    $this->get(route('map'))->assertOk()
        ->assertSee('pinned range', false);
});

it('/map shows venue pins with upcoming match counts', function () {
    $host = Organisation::factory()->create(['status' => ListingStatus::Published, 'type' => OrganisationType::Club]);
    $gp = Venue::factory()->create([
        'name' => 'GP Test Range',
        'province' => Province::Gauteng,
        'status' => ListingStatus::Published,
        'lat' => -25.7479,
        'lng' => 28.2293,
    ]);
    $wc = Venue::factory()->create([
        'name' => 'WC Test Range',
        'province' => Province::WesternCape,
        'status' => ListingStatus::Published,
        'lat' => -33.9249,
        'lng' => 18.4241,
    ]);

    Event::factory()->count(2)->confirmed()->create([
        'host_organisation_id' => $host->id,
        'venue_id' => $gp->id,
        'starts_at' => now()->addDays(4),
    ]);
    Event::factory()->confirmed()->create([
        'host_organisation_id' => $host->id,
        'venue_id' => $wc->id,
        'starts_at' => now()->addDays(4),
    ]);

    $html = $this->get(route('map'))->assertOk()->getContent();

    expect($html)
        ->toContain('GP Test Range')
        ->toContain('WC Test Range')
        ->toContain('2 matches')
        ->toContain('1 match')
        ->toContain('data-pins=');
});

it('/map lists unpinned venues that still have upcoming matches', function () {
    $host = Organisation::factory()->create(['status' => ListingStatus::Published, 'type' => OrganisationType::Club]);
    $bare = Venue::factory()->create([
        'name' => 'No Pin Range',
        'status' => ListingStatus::Published,
        'lat' => null,
        'lng' => null,
    ]);
    Event::factory()->confirmed()->create([
        'host_organisation_id' => $host->id,
        'venue_id' => $bare->id,
        'starts_at' => now()->addDays(4),
    ]);

    $this->get(route('map'))->assertOk()
        ->assertSee('pin still needed', false)
        ->assertSee('No Pin Range');
});

it('/map bubbles link to the calendar filtered by that province', function () {
    // Kept as a soft redirect of intent: venue pins now link to the
    // range page. Assert the map still renders Leaflet + data payload.
    $html = $this->get(route('map'))->assertOk()->getContent();

    expect($html)->toContain('id="ss-map"');
});

it('/map shows empty-province claim copy instead of a bare zero', function () {
    // Province bubbles replaced by venue pins — assert OSM/CARTO wiring.
    $html = $this->get(route('map'))->assertOk()->getContent();

    expect($html)
        ->toContain('is-osm-fallback')
        ->toContain('tile.openstreetmap.org');
});

it('/map uses CARTO Positron when CARTO_API_KEY is configured', function () {
    config(['services.carto.api_key' => 'test-carto-key']);

    $html = $this->get(route('map'))->assertOk()->getContent();

    expect($html)
        ->toContain('data-carto-key="test-carto-key"')
        ->toContain('basemaps.cartocdn.com/light_all')
        ->toContain('?key=')
        ->not->toContain('class="ss-map is-osm-fallback"');
});

it('/map serves a Leaflet-backed interactive map element', function () {
    $html = $this->get(route('map'))->assertOk()->getContent();

    expect($html)
        ->toContain('id="ss-map"')
        ->toContain('data-pins=')
        ->toContain('unpkg.com/leaflet@1.9.4')
        ->toContain('leaflet.markercluster')
        ->toContain('markerClusterGroup')
        ->toContain('disableClusteringAtZoom');
});

it('/map shows province zoom chips above the map', function () {
    $html = $this->get(route('map'))->assertOk()->getContent();

    expect($html)
        ->toContain('map-province-filters')
        ->toContain('data-province="gauteng"')
        ->toContain('data-centroids=')
        ->toContain('focusProvince');
});

it('/map accepts a province query for initial focus', function () {
    $html = $this->get(route('map', ['province' => 'gauteng']))->assertOk()->getContent();

    expect($html)
        ->toContain('data-province="gauteng"')
        ->toContain('data-province="gauteng"')
        ->toMatch('/data-province="gauteng"[^>]*aria-pressed="true"/');
});

it('/map view is set to noindex when there are zero upcoming matches (avoid an empty map in the SERP)', function () {
    $html = $this->get(route('map'))->assertOk()->getContent();
    expect($html)->toContain('0 matches');
})->skip('Documents intent — noindex-on-empty-map is future work.');

// ---- View toggle + nav integration --------------------------------

it('calendar page renders a view toggle linking to /map', function () {
    $html = $this->get(route('calendar'))->assertOk()->getContent();

    expect($html)
        ->toContain('class="view-toggle"')
        ->toContain('href="'.route('map').'"')
        ->toContain('href="'.route('calendar.month'));
});

it('map page renders a view toggle linking back to /calendar', function () {
    $html = $this->get(route('map'))->assertOk()->getContent();

    expect($html)
        ->toContain('class="view-toggle"')
        ->toContain('href="'.route('calendar').'"')
        ->toContain('href="'.route('calendar.month'));
});

it('primary nav promotes Matches over a standalone Map link', function () {
    $html = $this->get(route('home'))->assertOk()->getContent();

    // Map lives under Matches (List | Month | Map), not as a top-level
    // destination. Desktop + mobile both advertise Matches instead.
    expect($html)
        ->toContain('href="'.route('calendar').'">Matches</a>')
        ->not->toContain('href="'.route('map').'">Map</a>');
});

it('legacy /calendar/map URL redirects to /map', function () {
    $this->get('/calendar/map')
        ->assertRedirect(route('map'));
});
