<?php

use App\Enums\EventStatus;
use App\Enums\ListingStatus;
use App\Models\Event;
use App\Models\Organisation;

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
        ->assertSee('"@type":"Event"', false)
        ->assertSee('SEO Check Match', false);
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

it('ships a robots.txt with the sitemap and the desk disallow', function () {
    $path = public_path('robots.txt');

    expect(is_file($path))->toBeTrue();
    $body = (string) file_get_contents($path);

    expect($body)
        ->toContain('Sitemap: https://shootingsports.co.za/sitemap.xml')
        ->toContain('Disallow: /desk')
        ->toContain('Disallow: /admin')
        ->toContain('Disallow: /my-calendar');
});

it('serves the pages sitemap with static URLs and skips private surfaces', function () {
    $this->get('/sitemaps/pages.xml')
        ->assertOk()
        ->assertHeader('content-type', 'application/xml; charset=UTF-8')
        ->assertSee(route('home'), false)
        ->assertSee(route('calendar'), false)
        ->assertSee(route('privacy'), false)
        ->assertSee(route('embed.docs'), false)
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
        ->assertSee('/desk and /admin');
});

it('exposes the default share image', function () {
    expect(is_file(public_path('images/og-default.png')))->toBeTrue();
});
