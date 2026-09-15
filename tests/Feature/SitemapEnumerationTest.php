<?php

use App\Enums\EventStatus;
use App\Enums\ListingStatus;
use App\Enums\OrganisationType;
use App\Enums\ProviderCategory;
use App\Enums\Province;
use App\Models\Discipline;
use App\Models\Event;
use App\Models\Organisation;
use App\Models\Provider;
use App\Models\Venue;

beforeEach(function () {
    $this->withoutVite();
});

/**
 * Production-shaped smoke: every sitemap URL must be 200 with a valid
 * <urlset> or <sitemapindex>. Regression cover for the "sitemap.xml
 * returns 500" outage reported on shootingsports.co.za — this test
 * seeds one of every enum-driven variant, then walks every declared
 * sitemap route without touching the response body's schema.
 */
it('serves every sitemap endpoint with a rich, enum-covering dataset', function () {
    // Federation + club + provincial body — hits both branches of
    // Organisation::isFederationListing() when the controller decides
    // whether to point at clubs.show or federations.show.
    Organisation::factory()->create([
        'slug' => 'saprf-sitemap',
        'name' => 'SAPRF sitemap',
        'type' => OrganisationType::Federation,
        'status' => ListingStatus::Published,
    ]);
    Organisation::factory()->create([
        'slug' => 'cgpsa-sitemap',
        'name' => 'CGPSA sitemap',
        'type' => OrganisationType::ProvincialBody,
        'status' => ListingStatus::Published,
    ]);
    Organisation::factory()->create([
        'slug' => 'club-sitemap',
        'name' => 'Club sitemap',
        'type' => OrganisationType::Club,
        'status' => ListingStatus::Published,
    ]);
    Organisation::factory()->create([
        'slug' => 'archived-sitemap',
        'name' => 'Archived sitemap',
        'type' => OrganisationType::Club,
        'status' => ListingStatus::Archived,
    ]);

    Venue::factory()->create([
        'slug' => 'venue-sitemap',
        'name' => 'Venue sitemap',
        'status' => ListingStatus::Published,
    ]);

    // One event per non-draft status. Published scope includes
    // cancelled + completed, so all of these must survive the sort.
    foreach ([
        EventStatus::Planned,
        EventStatus::Confirmed,
        EventStatus::EntriesOpen,
        EventStatus::Full,
        EventStatus::Postponed,
        EventStatus::Cancelled,
        EventStatus::Completed,
    ] as $i => $status) {
        Event::factory()->create([
            'slug' => 'event-sitemap-'.$i,
            'title' => 'Event sitemap '.$i,
            'status' => $status,
            'starts_at' => now()->addDays($i),
        ]);
    }

    // One discipline per family so the discipline × province matrix runs
    // against real data, plus one unpublished discipline that must be
    // excluded from the sitemap.
    Discipline::factory()->create([
        'slug' => 'sitemap-precision',
        'name' => 'Sitemap Precision',
        'is_published' => true,
    ]);
    Discipline::factory()->create([
        'slug' => 'sitemap-draft',
        'name' => 'Sitemap Draft',
        'is_published' => false,
    ]);

    // One published provider per category exercises every category ×
    // province combination the providers sitemap builds.
    foreach (ProviderCategory::cases() as $i => $category) {
        Provider::factory()->create([
            'slug' => 'provider-'.$category->value,
            'name' => 'Provider '.$category->value,
            'category' => $category,
            'status' => ListingStatus::Published,
        ]);
    }

    $routes = [
        '/sitemap.xml',
        '/sitemaps/pages.xml',
        '/sitemaps/events.xml',
        '/sitemaps/organisations.xml',
        '/sitemaps/venues.xml',
        '/sitemaps/disciplines.xml',
        '/sitemaps/providers.xml',
    ];

    foreach ($routes as $path) {
        $response = $this->get($path);

        if ($response->status() !== 200) {
            $preview = substr((string) $response->getContent(), 0, 500);
            $this->fail("Sitemap {$path} returned {$response->status()}. Body: {$preview}");
        }

        expect($response->headers->get('content-type'))->toContain('application/xml');
    }
});

it('does not include archived organisations in the organisations sitemap', function () {
    Organisation::factory()->create([
        'slug' => 'live-org',
        'status' => ListingStatus::Published,
    ]);
    Organisation::factory()->create([
        'slug' => 'archived-org',
        'status' => ListingStatus::Archived,
    ]);

    $this->get('/sitemaps/organisations.xml')
        ->assertOk()
        ->assertSee('live-org', false)
        ->assertDontSee('archived-org', false);
});

it('does not include draft events in the events sitemap', function () {
    Event::factory()->create([
        'slug' => 'live-event',
        'status' => EventStatus::Confirmed,
    ]);
    Event::factory()->create([
        'slug' => 'draft-event',
        'status' => EventStatus::Draft,
    ]);

    $this->get('/sitemaps/events.xml')
        ->assertOk()
        ->assertSee('live-event', false)
        ->assertDontSee('draft-event', false);
});

it('includes every province slug in the disciplines sitemap', function () {
    Discipline::factory()->create([
        'slug' => 'coverage-check',
        'is_published' => true,
    ]);

    $response = $this->get('/sitemaps/disciplines.xml');

    $response->assertOk();

    foreach (Province::cases() as $province) {
        $response->assertSee('/disciplines/coverage-check/'.$province->urlSlug(), false);
    }
});

it('includes every category slug in the providers sitemap', function () {
    $response = $this->get('/sitemaps/providers.xml');

    $response->assertOk();

    foreach (ProviderCategory::cases() as $category) {
        $response->assertSee('/suppliers/'.$category->urlSlug(), false);
    }
});

/**
 * The sitemap controller must survive a single row that throws while
 * building its URL — one dud match should not turn the entire sitemap
 * into a 500 for Search Console. We simulate that by faking a bad
 * scenario: two published events, one with a normal slug and one with
 * a slug we blank out post-insert (bypassing the NOT NULL by using an
 * update that would corrupt route() generation in the wild).
 */
it('drops a bad row instead of 500ing the whole events sitemap', function () {
    $good = Event::factory()->create([
        'slug' => 'sitemap-survivor',
        'status' => EventStatus::Confirmed,
    ]);

    // The controller's safeUrl guard catches Throwables from route().
    // The cheapest way to force one is to give an event a slug that
    // Laravel's URL generator rejects — a slug with a leading slash
    // trips route() when combined with the {event:slug} binding
    // pattern in strict mode.
    $bad = Event::factory()->create([
        'slug' => 'sitemap-doomed',
        'status' => EventStatus::Confirmed,
    ]);
    // Blank the slug directly in the database — bypasses the NOT NULL
    // constraint on some drivers (sqlite in tests happily accepts an
    // empty string, which is what production DBs sometimes end up with
    // after a broken import).
    DB::table('events')->where('id', $bad->id)->update(['slug' => '']);

    $response = $this->get('/sitemaps/events.xml');

    // The whole sitemap must still be 200 XML, and the good event must
    // be listed even though the bad row was quietly dropped.
    $response->assertOk();
    expect($response->headers->get('content-type'))->toContain('application/xml');
    $response->assertSee('sitemap-survivor', false);
});
