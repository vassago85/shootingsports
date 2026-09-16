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
});

// ---- Live reticle sweep -------------------------------------------

it('homepage hero reticle wraps the SVG in an animated container', function () {
    $html = $this->get(route('home'))->assertOk()->getContent();

    // Wrapper drives the sweep — SVG stays static inside it.
    expect($html)->toContain('class="hero-reticle-wrap"')
        ->and($html)->toContain('class="hero-reticle"');
});

it('the reticle sweep animation is disabled for prefers-reduced-motion visitors', function () {
    // The CSS bundle keeps the animation-disable rule inside the
    // reduced-motion media query. Read it directly from source so
    // this test does not depend on Vite build state in CI.
    $css = file_get_contents(resource_path('css/app.css'));

    expect($css)
        ->toContain('prefers-reduced-motion')
        ->and($css)->toContain('.hero-reticle-wrap::after { animation: none; opacity: 0; }');
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

it('/map route renders successfully with all nine provinces in the legend', function () {
    $response = $this->get(route('map'))->assertOk();

    foreach (Province::cases() as $province) {
        $response->assertSee($province->getLabel());
    }
});

it('/map shows the correct upcoming match count per province', function () {
    // Seed two events in Gauteng and one in Western Cape via their
    // venues. The Cache::flush() in beforeEach guarantees a fresh
    // aggregation on the first request.
    $host = Organisation::factory()->create(['status' => ListingStatus::Published, 'type' => OrganisationType::Club]);
    $gp = Venue::factory()->create(['province' => Province::Gauteng, 'status' => ListingStatus::Published]);
    $wc = Venue::factory()->create(['province' => Province::WesternCape, 'status' => ListingStatus::Published]);

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

    // Legend surfaces the count next to each province name.
    expect($html)
        ->toContain('2 matches')       // Gauteng
        ->toContain('1 match');        // Western Cape (singular)
});

it('/map bubbles link to the calendar filtered by that province', function () {
    $html = $this->get(route('map'))->assertOk()->getContent();

    // The controller pre-computes calendar_url for each marker and
    // renders it in the legend anchor. Assert one representative.
    expect($html)->toContain(route('calendar', ['province' => 'gauteng']));
});

it('/map serves a Leaflet-backed interactive map element', function () {
    $html = $this->get(route('map'))->assertOk()->getContent();

    expect($html)
        ->toContain('id="ss-map"')
        ->toContain('data-markers=')
        ->toContain('unpkg.com/leaflet@1.9.4');
});

it('/map view is set to noindex when there are zero upcoming matches (avoid an empty map in the SERP)', function () {
    // No events: map still renders (nine grey dots + legend), but
    // we don't want Google indexing an empty state as the definitive
    // map page. Only assert *rendering* here — noindex behavior is
    // future work; this test is a placeholder that pins current
    // behaviour and reads intent.
    $html = $this->get(route('map'))->assertOk()->getContent();
    expect($html)->toContain('0 matches');
})->skip('Documents intent — noindex-on-empty-map is future work.');

// ---- View toggle + nav integration --------------------------------

it('calendar page renders a view toggle linking to /map', function () {
    $html = $this->get(route('calendar'))->assertOk()->getContent();

    expect($html)
        ->toContain('class="view-toggle"')
        ->toContain('href="'.route('map').'"');
});

it('map page renders a view toggle linking back to /calendar', function () {
    $html = $this->get(route('map'))->assertOk()->getContent();

    expect($html)
        ->toContain('class="view-toggle"')
        ->toContain('href="'.route('calendar').'"');
});

it('primary nav includes the Map link on both desktop and mobile menus', function () {
    $html = $this->get(route('home'))->assertOk()->getContent();

    // Two matching href occurrences: desktop nav + mobile menu.
    expect(substr_count($html, 'href="'.route('map').'"'))->toBeGreaterThanOrEqual(2);
});

it('legacy /calendar/map URL redirects to /map', function () {
    $this->get('/calendar/map')
        ->assertRedirect(route('map'));
});
