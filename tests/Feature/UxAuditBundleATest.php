<?php

use App\Enums\ListingStatus;
use App\Enums\OrganisationType;
use App\Enums\ProviderCategory;
use App\Enums\Province;
use App\Enums\VerificationState;
use App\Models\Discipline;
use App\Models\Event;
use App\Models\Organisation;
use App\Models\Venue;
use App\Support\Geo;
use Illuminate\Support\Facades\Cache;

beforeEach(function () {
    $this->withoutVite();
    Cache::flush();
});

// ---- #1 Industry empty states + advertise gate --------------------

it('suppliers index links empty categories to the category page', function () {
    $html = $this->get(route('suppliers.index'))->assertOk()->getContent();
    $category = ProviderCategory::cases()[0];

    expect($html)
        ->toContain('Free to list. Open this category')
        ->toContain(route('suppliers.category', $category->urlSlug()))
        ->not->toContain('0 listed');
});

it('suppliers category empty state links to claim', function () {
    $category = ProviderCategory::cases()[0];

    $this->get(route('suppliers.category', $category->urlSlug()))
        ->assertOk()
        ->assertSee('Free to list')
        ->assertSee(route('claim'));
});

// ---- #3 Unconfirmed badge silent ----------------------------------

it('club rows do not print an Unconfirmed badge', function () {
    Organisation::factory()->create([
        'status' => ListingStatus::Published,
        'type' => OrganisationType::Club,
        'verification_state' => VerificationState::Unconfirmed,
        'last_verified_at' => null,
        'name' => 'Quiet Club',
    ]);

    $html = $this->get(route('clubs.index'))->assertOk()->getContent();

    expect($html)->toContain('Quiet Club')
        ->and($html)->not->toContain('Unconfirmed');
});

// ---- #4 Stat bar --------------------------------------------------

it('homepage stat bar shows Matches Ranges Disciplines Clubs & series', function () {
    $html = $this->get(route('home'))->assertOk()->getContent();

    expect($html)
        ->toContain('<span>Matches</span>')
        ->toContain('<span>Ranges</span>')
        ->toContain('<span>Disciplines</span>')
        ->toContain('<span>Clubs &amp; series</span>')
        ->not->toContain('<span>Provinces</span>')
        ->not->toContain('<span>Upcoming matches</span>');
});

// ---- #2 Discover collapse -----------------------------------------

it('home asks for a division instead of listing every sport', function () {
    Discipline::factory()->create([
        'name' => 'Lonely Field Target',
        'is_published' => true,
        'parent_id' => null,
    ]);

    $html = $this->get(route('home'))->assertOk()->getContent();

    expect($html)
        ->toContain('What are you interested in?')
        ->not->toContain('Show all')
        ->not->toContain('No matches listed yet');
});

it('empty discipline match list prompts follow', function () {
    $discipline = Discipline::factory()->create([
        'name' => 'Field Target',
        'is_published' => true,
        'parent_id' => null,
    ]);

    $this->get(route('disciplines.show', $discipline->slug))
        ->assertOk()
        ->assertSee('Nobody has listed a Field Target match yet')
        ->assertSee('Follow this discipline');
});

// ---- #5 Map geo + tiles -------------------------------------------

it('every province centroid sits inside the South Africa bounding box', function () {
    foreach (Province::cases() as $province) {
        [$lat, $lng] = Geo::provinceCentroid($province);
        expect(Geo::isInsideSouthAfrica($lat, $lng))->toBeTrue(
            "{$province->name} centroid {$lat},{$lng} is outside SA"
        );
    }
});

// ---- #6 Match page facts-first ------------------------------------

it('match page links the venue and exposes Directions', function () {
    $host = Organisation::factory()->create([
        'status' => ListingStatus::Published,
        'type' => OrganisationType::Club,
        'name' => 'Test Rifle Club',
    ]);
    $venue = Venue::factory()->create([
        'status' => ListingStatus::Published,
        'name' => 'Test Range',
        'town' => 'Pretoria',
        'province' => Province::Gauteng,
        'lat' => -25.7479,
        'lng' => 28.2293,
    ]);
    $event = Event::factory()->create([
        'host_organisation_id' => $host->id,
        'venue_id' => $venue->id,
        'entry_url' => 'https://example.test/enter',
        'status' => 'confirmed',
    ]);

    $response = $this->get(route('matches.show', $event->slug))->assertOk();

    $response->assertSee('match-layout', false)
        ->assertSee('Enter here', false)
        ->assertSee('Directions', false)
        ->assertSee(route('ranges.show', $venue->slug), false)
        ->assertSee('Test Rifle Club', false)
        ->assertSee('Test Range', false)
        ->assertDontSee('Host club');
});
