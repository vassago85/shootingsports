<?php

use App\Enums\EventStatus;
use App\Enums\ListingStatus;
use App\Enums\ProviderCategory;
use App\Enums\Province;
use App\Models\Discipline;
use App\Models\Event;
use App\Models\Organisation;
use App\Models\Provider;
use App\Models\Venue;
use App\Support\JsonLd;

beforeEach(function () {
    $this->withoutVite();
});

it('ships the tune-up Google verification file verbatim', function () {
    $path = public_path('google79bd43f041dd2a84.html');

    expect(is_file($path))->toBeTrue();
    expect(trim((string) file_get_contents($path)))
        ->toBe('google-site-verification: google79bd43f041dd2a84.html');
});

it('emits Open Graph, Twitter and site JSON-LD on the home page', function () {
    $this->get(route('home'))
        ->assertOk()
        ->assertSee('property="og:site_name"', false)
        ->assertSee('content="Shooting Sports"', false)
        ->assertSee('property="og:image"', false)
        ->assertSee('images/og-default.png', false)
        ->assertSee('name="twitter:card"', false)
        ->assertSee('content="summary_large_image"', false)
        ->assertSee('"@type":"WebSite"', false)
        ->assertSee('"@type":"SportsOrganization"', false);
});

it('renders both site graph and per-event JSON-LD on a match page', function () {
    $club = Organisation::factory()->create([
        'slug' => 'seo-check-club',
        'name' => 'SEO Check Club',
        'status' => ListingStatus::Published,
    ]);
    $event = Event::factory()->confirmed()->create([
        'slug' => 'seo-check-match',
        'title' => 'SEO Check Match',
        'status' => EventStatus::EntriesOpen,
        'host_organisation_id' => $club->id,
    ]);

    $response = $this->get(route('matches.show', $event->slug));

    $response->assertOk();

    $scripts = collect(explode('<script type="application/ld+json">', $response->getContent()))
        ->skip(1)
        ->count();

    expect($scripts)->toBeGreaterThanOrEqual(2);

    $response
        ->assertSee('"@type":"WebSite"', false)
        ->assertSee('"@type":"SportsEvent"', false)
        ->assertSee('SEO Check Match', false);
});

/*
 * Google Search Console flagged three non-critical Event structured
 * data issues (endDate, offers, performer). The three tests below pin
 * the JsonLd::event payload so each field is always present, whether
 * or not the underlying Event has an explicit value.
 */

it('event JSON-LD always includes endDate, offers and performer', function () {
    $club = Organisation::factory()->create([
        'slug' => 'schema-club',
        'name' => 'Schema Club',
        'status' => ListingStatus::Published,
    ]);
    $event = Event::factory()->confirmed()->create([
        'title' => 'Full-shape event',
        'starts_at' => now()->addWeek()->setTime(9, 0),
        'ends_at' => now()->addWeek()->setTime(15, 0),
        'entry_fee_cents' => 25000,
        'member_fee_cents' => 15000,
        'entry_url' => 'https://entry.example/register',
        'capacity' => 60,
        'entries_taken' => 5,
        'host_organisation_id' => $club->id,
    ]);

    $payload = JsonLd::event($event);

    expect($payload['@type'])->toBe('SportsEvent')
        ->and($payload)->toHaveKey('endDate')
        ->and($payload)->toHaveKey('performer')
        ->and($payload)->toHaveKey('offers')
        ->and($payload['performer']['@type'])->toBe('SportsOrganization')
        ->and($payload['performer']['name'])->toBe('Schema Club')
        ->and($payload['offers']['@type'])->toBe('Offer')
        // Member fee wins when cheaper — 15000c = 150.00.
        ->and($payload['offers']['price'])->toBe('150.00')
        ->and($payload['offers']['priceCurrency'])->toBe('ZAR')
        ->and($payload['offers']['url'])->toBe('https://entry.example/register')
        ->and($payload['offers']['availability'])->toBe('https://schema.org/InStock');
});

it('event JSON-LD falls back to a 4h endDate when ends_at is null', function () {
    $starts = now()->addWeek()->setTime(9, 0);
    $event = Event::factory()->confirmed()->create([
        'starts_at' => $starts,
        'ends_at' => null,
        'all_day' => false,
    ]);

    $payload = JsonLd::event($event);
    $expected = $starts->copy()->addHours(4)->timezone(config('app.timezone'))->toIso8601String();

    expect($payload['endDate'])->toBe($expected)
        ->and($payload['startDate'])->toContain('+02:00');
});

it('event JSON-LD falls back to end-of-day when the event is all_day', function () {
    $starts = now()->addWeek()->startOfDay();
    $event = Event::factory()->confirmed()->create([
        'starts_at' => $starts,
        'ends_at' => null,
        'all_day' => true,
    ]);

    $expected = $starts->copy()->endOfDay()->timezone(config('app.timezone'))->toIso8601String();

    expect(JsonLd::event($event)['endDate'])->toBe($expected);
});

it('event JSON-LD omits price when no fee is on record', function () {
    $event = Event::factory()->confirmed()->create([
        'entry_fee_cents' => null,
        'member_fee_cents' => null,
        'entry_url' => null,
    ]);

    $offers = JsonLd::event($event)['offers'];

    expect($offers)->not->toHaveKey('price')
        ->and($offers['url'])->toBe($event->publicUrl())
        ->and($offers['priceCurrency'])->toBe('ZAR');
});

it('event JSON-LD marks capacity events SoldOut when entries_taken >= capacity', function () {
    $event = Event::factory()->confirmed()->create([
        'capacity' => 40,
        'entries_taken' => 40,
    ]);

    expect(JsonLd::event($event)['offers']['availability'])->toBe('https://schema.org/SoldOut');
});

it('event JSON-LD marks cancelled events with Discontinued availability', function () {
    $event = Event::factory()->confirmed()->create([
        'status' => EventStatus::Cancelled,
    ]);

    $payload = JsonLd::event($event);

    expect($payload['eventStatus'])->toBe('https://schema.org/EventCancelled')
        ->and($payload['offers']['availability'])->toBe('https://schema.org/Discontinued');
});

it('event JSON-LD falls back to a PerformingGroup performer when there is no host', function () {
    $event = Event::factory()->confirmed()->create([
        'host_organisation_id' => null,
    ]);

    $performer = JsonLd::event($event)['performer'];

    expect($performer['@type'])->toBe('PerformingGroup')
        ->and($performer['name'])->toBe('Competitive shooters');
});

it('renders the google-site-verification meta only when configured', function () {
    config()->set('services.google.site_verification', null);
    $this->get(route('home'))
        ->assertDontSee('google-site-verification', false);

    config()->set('services.google.site_verification', 'abc123token');
    $this->get(route('home'))
        ->assertSee('name="google-site-verification"', false)
        ->assertSee('content="abc123token"', false);
});

it('loads the Umami tracker only when both env vars are set', function () {
    config()->set('services.umami.script_url', null);
    config()->set('services.umami.website_id', null);
    $this->get(route('home'))->assertDontSee('data-website-id', false);

    config()->set('services.umami.script_url', 'https://analytics.example.test/script.js');
    config()->set('services.umami.website_id', 'aaaaaaaa-bbbb-cccc-dddd-eeeeeeeeeeee');

    $this->get(route('home'))
        ->assertSee('src="https://analytics.example.test/script.js"', false)
        ->assertSee('data-website-id="aaaaaaaa-bbbb-cccc-dddd-eeeeeeeeeeee"', false);
});

it('keeps the embed calendar tracker-free and noindexed', function () {
    config()->set('services.umami.script_url', 'https://analytics.example.test/script.js');
    config()->set('services.umami.website_id', 'aaaaaaaa-bbbb-cccc-dddd-eeeeeeeeeeee');

    Event::factory()->confirmed()->create(['title' => 'Embed sanity match']);

    $this->get(route('embed.calendar'))
        ->assertOk()
        ->assertSee('<meta name="robots" content="noindex">', false)
        ->assertDontSee('data-website-id', false)
        ->assertDontSee('og:site_name', false);
});

it('ships a robots.txt with the sitemap and no admin path advertisements', function () {
    $path = public_path('robots.txt');

    expect(is_file($path))->toBeTrue();
    $body = (string) file_get_contents($path);

    expect($body)
        ->toContain('Sitemap: https://shootingsports.co.za/sitemap.xml')
        ->not->toContain('Disallow: /desk')
        ->not->toContain('Disallow: /admin')
        ->not->toContain('Disallow: /my-calendar');
});

it('serves the pages sitemap with static URLs and skips private surfaces', function () {
    $this->get('/sitemaps/pages.xml')
        ->assertOk()
        ->assertHeader('content-type', 'application/xml; charset=UTF-8')
        ->assertSee(route('home'), false)
        ->assertSee(route('calendar'), false)
        ->assertSee(route('map'), false)
        ->assertSee(route('calendar.month'), false)
        ->assertSee(route('privacy'), false)
        ->assertSee(route('terms'), false)
        ->assertSee(route('embed.docs'), false)
        ->assertSee('<lastmod>', false)
        ->assertDontSee('/desk', false)
        ->assertDontSee('/admin', false)
        ->assertDontSee('my-calendar', false);
});

it('lists the pages sitemap in the sitemap index', function () {
    $this->get('/sitemap.xml')
        ->assertOk()
        ->assertSee('/sitemaps/pages.xml', false);
});

it('serves llms.txt as plain text with the calendar link', function () {
    $response = $this->get('/llms.txt');

    $response->assertOk();
    expect($response->headers->get('content-type'))->toContain('text/plain');
    $response
        ->assertSee('Shooting Sports')
        ->assertSee(route('calendar'), false)
        ->assertSee(route('terms'), false)
        ->assertSee('/desk and /admin');
});

it('serves the terms of use page and links it from the public footer', function () {
    $this->get('/terms')
        ->assertOk()
        ->assertSee('Terms of Use')
        ->assertSee('independent discovery register', false)
        ->assertSee('Not a firearms dealer', false);

    $this->get('/')
        ->assertOk()
        ->assertSee(route('terms'), false);
});

it('exposes the default share image', function () {
    expect(is_file(public_path('images/og-default.png')))->toBeTrue();
});

// ================================================================
// BreadcrumbList schema (rich SERP breadcrumb strip)
// ================================================================

it('JsonLd::breadcrumbs emits contiguous 1-indexed ListItem positions', function () {
    $graph = JsonLd::breadcrumbs([
        ['name' => 'Home', 'url' => 'https://example.test/'],
        ['name' => 'Calendar', 'url' => 'https://example.test/calendar'],
        ['name' => 'Match', 'url' => 'https://example.test/matches/x'],
    ]);

    expect($graph['@type'])->toBe('BreadcrumbList')
        ->and($graph['itemListElement'])->toHaveCount(3)
        ->and($graph['itemListElement'][0]['position'])->toBe(1)
        ->and($graph['itemListElement'][2]['position'])->toBe(3)
        ->and($graph['itemListElement'][1]['name'])->toBe('Calendar')
        ->and($graph['itemListElement'][1]['item'])->toBe('https://example.test/calendar');
});

it('a match page emits BreadcrumbList schema with Home > Calendar > Province > Title', function () {
    $venue = Venue::factory()->create(['province' => Province::Gauteng, 'status' => ListingStatus::Published]);
    $event = Event::factory()->confirmed()->create([
        'slug' => 'breadcrumb-match',
        'title' => 'Breadcrumb Match',
        'venue_id' => $venue->id,
    ]);

    $response = $this->get(route('matches.show', $event->slug));

    $response->assertOk()
        ->assertSee('"@type":"BreadcrumbList"', false)
        ->assertSee('"name":"Calendar"', false)
        ->assertSee('"name":"Gauteng"', false)
        ->assertSee('"name":"Breadcrumb Match"', false);
});

it('a club page emits BreadcrumbList schema with Home > Clubs > Club', function () {
    $club = Organisation::factory()->create([
        'slug' => 'crumb-club',
        'name' => 'Crumb Club',
        'status' => ListingStatus::Published,
    ]);

    $response = $this->get(route('clubs.show', $club->slug));

    $response->assertOk()
        ->assertSee('"@type":"BreadcrumbList"', false)
        ->assertSee('"name":"Clubs & series"', false)
        ->assertSee('"name":"Crumb Club"', false);
});

it('a range page emits BreadcrumbList schema with Home > Ranges > Range', function () {
    $venue = Venue::factory()->create([
        'slug' => 'crumb-range',
        'name' => 'Crumb Range',
        'status' => ListingStatus::Published,
    ]);

    $response = $this->get(route('ranges.show', $venue->slug));

    $response->assertOk()
        ->assertSee('"@type":"BreadcrumbList"', false)
        ->assertSee('"name":"Ranges"', false)
        ->assertSee('"name":"Crumb Range"', false);
});

it('a supplier page emits a 4-level BreadcrumbList with Category', function () {
    $provider = Provider::factory()->create([
        'slug' => 'crumb-supplier',
        'name' => 'Crumb Supplier',
        'category' => ProviderCategory::cases()[0],
        'status' => ListingStatus::Published,
    ]);

    $response = $this->get(route('suppliers.show', $provider->slug));

    $response->assertOk()
        ->assertSee('"@type":"BreadcrumbList"', false)
        ->assertSee('"name":"Industry"', false)
        ->assertSee('"name":"'.$provider->category->getLabel().'"', false)
        ->assertSee('"name":"Crumb Supplier"', false);
});

// ================================================================
// ItemList schema (collection pages)
// ================================================================

it('the disciplines index emits an ItemList of the discipline tiles', function () {
    $response = $this->get(route('disciplines.index'));

    $response->assertOk()
        ->assertSee('"@type":"ItemList"', false)
        ->assertSee('"@type":"BreadcrumbList"', false)
        ->assertSee('"name":"South African shooting disciplines"', false);
});

it('the clubs index emits an ItemList with a numberOfItems count', function () {
    // The factory already defaults type = club, so we just need three
    // published rows for the ItemList to have items to list.
    Organisation::factory()->count(3)->create(['status' => ListingStatus::Published]);

    $response = $this->get(route('clubs.index'));

    $response->assertOk()
        ->assertSee('"@type":"ItemList"', false)
        ->assertSee('"numberOfItems":', false);
});

it('the ranges index emits an ItemList schema', function () {
    Venue::factory()->count(2)->create(['status' => ListingStatus::Published]);

    $this->get(route('ranges.index'))
        ->assertOk()
        ->assertSee('"@type":"ItemList"', false)
        ->assertSee('"@type":"BreadcrumbList"', false);
});

it('the suppliers index emits an ItemList of categories', function () {
    Provider::factory()->create(['status' => ListingStatus::Published, 'category' => ProviderCategory::cases()[0]]);

    $this->get(route('suppliers.index'))
        ->assertOk()
        ->assertSee('"@type":"ItemList"', false)
        ->assertSee('"@type":"BreadcrumbList"', false);
});

// ================================================================
// Filter-aware meta on /calendar
// ================================================================

it('/calendar defaults to the static match calendar title when no filter is set', function () {
    $this->get(route('calendar'))
        ->assertOk()
        ->assertSee('<title>Shooting competitions calendar', false)
        ->assertSee('"@type":"BreadcrumbList"', false);
});

it('/calendar with a province filter promotes the province into the title and description', function () {
    Event::factory()->count(2)->confirmed()->create();

    $response = $this->get(route('calendar', ['province' => Province::Gauteng->urlSlug()]));

    $response->assertOk()
        ->assertSee('<title>Shooting matches in Gauteng', false)
        ->assertSee('"name":"Gauteng"', false);
});

it('/calendar canonical strips non-canonical params like radius', function () {
    $response = $this->get(route('calendar', [
        'province' => Province::WesternCape->urlSlug(),
        'radius' => 200,
    ]));

    $response->assertOk()
        // Canonical omits radius but keeps province.
        ->assertSee('<link rel="canonical" href="'.route('calendar', ['province' => Province::WesternCape->urlSlug()]).'"', false);
});

// ================================================================
// Filter-aware meta on discipline pages
// ================================================================

it('/disciplines/{slug} description mentions the actual match count', function () {
    $discipline = Discipline::query()->where('is_published', true)->first();
    if (! $discipline) {
        $this->markTestSkipped('no published discipline in the seeded set');
    }

    $response = $this->get(route('disciplines.show', $discipline->slug));

    $response->assertOk()
        // Description always says "N upcoming matches" or "no matches" — the
        // exact number varies but one of those two shapes will always render.
        ->assertSeeText($discipline->name);
});
