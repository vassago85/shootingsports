<?php

use App\Models\Event;
use App\Models\Organisation;
use App\Models\User;

beforeEach(function () {
    $this->withoutVite();
});

// ---- Card cleanup (audit #5, #6, #7, #9, #10) ---------------------

it('event card no longer prints the "No match banner yet" placeholder', function () {
    $event = Event::factory()->create();

    $html = view('components.event-card', ['event' => $event])->render();

    expect($html)->not->toContain('No match banner yet')
        ->and($html)->not->toContain('No entry link yet');
});

it('event card no longer duplicates the title in the banner (only one h3)', function () {
    $event = Event::factory()->create(['title' => 'National Championship 2026']);

    $html = view('components.event-card', ['event' => $event])->render();

    // banner-caption class is gone; title lives in the h3 only.
    expect($html)->not->toContain('banner-caption')
        // Title appears exactly once (inside the h3 anchor).
        ->and(substr_count($html, 'National Championship 2026'))->toBe(1);
});

it('event card makes the whole card clickable via the title anchor', function () {
    $event = Event::factory()->create();

    $html = view('components.event-card', ['event' => $event])->render();

    // The whole-card link pattern: an anchor with class dope-title-link
    // pointing at the match detail page. The CSS ::after rule stretches
    // it across the article — we assert the anchor + class combo.
    expect($html)->toContain('class="dope-title-link"')
        ->and($html)->toContain('href="'.route('matches.show', $event->slug).'"');
});

it('event card renders a filled Entry primary button only when entry_url exists', function () {
    $withEntry = Event::factory()->create(['entry_url' => 'https://example.test/enter']);
    $withoutEntry = Event::factory()->create(['entry_url' => null]);

    $withHtml = view('components.event-card', ['event' => $withEntry])->render();
    $withoutHtml = view('components.event-card', ['event' => $withoutEntry])->render();

    // With: primary button rendered
    expect($withHtml)
        ->toContain('class="dope-primary"')
        ->toContain('Entry details')
        ->toContain('https://example.test/enter');

    // Without: no dope-foot at all (audit #7 - no dead "No entry link" placeholder)
    expect($withoutHtml)
        ->not->toContain('dope-primary')
        ->not->toContain('dope-foot')
        ->not->toContain('Entry details');
});

// ---- Branded 404 (audit #4) --------------------------------------

it('a nonexistent URL renders the branded 404 page with brand + nav', function () {
    $response = $this->get('/directory');

    $response->assertStatus(404)
        ->assertSee('Off the plate')
        ->assertSee('not in the register')
        // Global nav is present (via x-layouts.public shell)
        ->assertSee(route('calendar'), false)
        ->assertSee(route('disciplines.index'), false)
        ->assertSee(route('clubs.index'), false)
        ->assertSee(route('ranges.index'), false);
});

it('the 404 page includes a search form pointing at the calendar', function () {
    $response = $this->get('/completely-made-up-slug-that-does-not-exist');

    $response->assertStatus(404)
        ->assertSee('Search the calendar')
        // Form action targets the calendar route
        ->assertSee('action="'.route('calendar').'"', false)
        ->assertSee('type="search"', false)
        ->assertSee('name="q"', false);
});

it('the 404 page is set to noindex, nofollow so Google does not index the placeholder text', function () {
    $this->get('/some-missing-page')
        ->assertStatus(404)
        ->assertSee('noindex, nofollow', false);
});

it('the 404 page still works when the visitor is authenticated (nav shows my calendar / my log)', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get('/another-missing-page')
        ->assertStatus(404)
        ->assertSee('My calendar')
        ->assertSee('My log');
});

// ---- Regression sweep ---------------------------------------------

it('the calendar page still renders event cards after the card rewrite', function () {
    $host = Organisation::factory()->create(['status' => 'published']);
    Event::factory()->count(2)->create([
        'host_organisation_id' => $host->id,
        'status' => 'confirmed',
    ]);

    $this->get('/calendar')
        ->assertOk()
        ->assertSee('class="dope', false); // dope-grid + dope article
});
