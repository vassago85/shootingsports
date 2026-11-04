<?php

use App\Enums\ListingStatus;
use App\Enums\Province;
use App\Jobs\PingIndexNow;
use App\Models\Event;
use App\Models\Organisation;
use App\Models\Venue;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\URL;

beforeEach(function () {
    $this->withoutVite();
    config([
        'app.url' => 'https://shootingsports.co.za',
        'seo.indexable' => true,
        'coming-soon.enabled' => false,
    ]);
    URL::forceRootUrl('https://shootingsports.co.za');
    URL::forceScheme('https');
});

it('allows every crawler and disallows only private prefixes', function () {
    $response = $this->get('/robots.txt');

    $response->assertOk();
    expect($response->headers->get('Content-Type'))->toContain('text/plain');

    $lines = preg_split('/\r\n|\n/', trim($response->getContent()));

    expect($lines)->toContain('User-agent: *')
        ->toContain('Allow: /')
        ->toContain('Disallow: /admin')
        ->toContain('Disallow: /desk')
        ->toContain('Disallow: /me')
        ->toContain('Sitemap: https://shootingsports.co.za/sitemap.xml')
        ->not->toContain('Disallow: /');

    $body = $response->getContent();

    foreach ([
        'Googlebot',
        'Bingbot',
        'ClaudeBot',
        'Claude-User',
        'Claude-SearchBot',
        'GPTBot',
        'OAI-SearchBot',
        'ChatGPT-User',
        'PerplexityBot',
        'Google-Extended',
        'Applebot',
    ] as $agent) {
        expect($body)->toContain('User-agent: '.$agent);
    }
});

it('serves a schema-valid sitemap index of https urls', function () {
    $response = $this->get('/sitemap.xml');

    $response->assertOk();
    expect($response->headers->get('Content-Type'))->toContain('application/xml');

    $xml = simplexml_load_string($response->getContent());

    expect($xml)->not->toBeFalse()
        ->and($xml->getName())->toBe('sitemapindex');

    $locs = [];
    foreach ($xml->sitemap as $sitemap) {
        $locs[] = (string) $sitemap->loc;
    }

    expect($locs)->not->toBeEmpty();

    foreach ($locs as $loc) {
        expect($loc)->toStartWith('https://shootingsports.co.za/sitemaps/');
    }
});

it('omits a fake lastmod on static sitemap pages and leaves weekend URLs out', function () {
    $xml = $this->get('/sitemaps/pages.xml')
        ->assertOk()
        ->assertSee('https://shootingsports.co.za', false)
        ->getContent();

    // Static pages have no `lastmod` — we do not pretend they changed
    // today. Weekend URLs are humans-only time windows; they
    // canonicalise to /calendar and never enter the sitemap.
    expect($xml)->not->toMatch('/<loc>https:\/\/shootingsports\.co\.za<\/loc>\s*<lastmod>/')
        ->and($xml)->not->toContain('/this-weekend')
        ->and($xml)->not->toContain('/next-weekend');
});

it('leaves key public routes indexable with a canonical on the https host', function (string $path) {
    $response = $this->get($path);

    $response->assertOk()
        ->assertDontSee('<meta name="robots"', false);

    expect($response->getContent())->toMatch('/<link rel="canonical" href="https:\/\/shootingsports\.co\.za[^"?]*">/');
})->with([
    '/',
    '/disciplines',
    '/suppliers',
    '/clubs',
    '/calendar',
    '/ranges',
]);

it('canonicalises calendar filter query strings onto the unfiltered calendar', function () {
    $this->get('/calendar?family=rifle&novice=1&from=2026-01-01&to=2026-06-01&weekend=1')
        ->assertOk()
        ->assertSee('<link rel="canonical" href="https://shootingsports.co.za/calendar">', false)
        ->assertSee('<meta name="robots" content="noindex,follow">', false);
});

it('canonicalises a province calendar onto the unfiltered calendar and noindexes the filter view', function () {
    // `/calendar?province=gauteng` is a UI state, not a canonical URL
    // — the indexable surface for Gauteng matches lives at
    // /disciplines/{slug}/gauteng. Collapse the filter view with a
    // bare canonical and noindex,follow so Google doesn't pick it.
    $this->get('/calendar?province=gauteng&radius=50&family=rifle')
        ->assertOk()
        ->assertSee('<link rel="canonical" href="https://shootingsports.co.za/calendar">', false)
        ->assertSee('<meta name="robots" content="noindex,follow">', false);
});

it('strips tracking parameters from the calendar canonical', function () {
    $this->get('/calendar?utm_source=newsletter&utm_campaign=weekend&fbclid=abc123')
        ->assertOk()
        ->assertSee('<link rel="canonical" href="https://shootingsports.co.za/calendar">', false)
        ->assertSee('<meta name="robots" content="noindex,follow">', false);
});

it('redirects an uppercase content path to its lowercase canonical', function () {
    $event = Event::factory()->confirmed()->create([
        'slug' => 'uppercase-match',
        'starts_at' => now()->addWeek(),
    ]);

    $this->get('/Events/'.$event->slug)
        ->assertStatus(301)
        ->assertRedirect('https://shootingsports.co.za/events/'.$event->slug);

    $this->get('/CLUBS')
        ->assertStatus(301)
        ->assertRedirect('https://shootingsports.co.za/clubs');
});

it('canonicalises a filtered directory index to the bare route and noindexes the filter view', function () {
    foreach (['/clubs?province=gauteng', '/ranges?province=gauteng', '/suppliers?q=rifle'] as $path) {
        $response = $this->get($path);

        $response->assertOk();

        $base = explode('?', $path, 2)[0];
        $html = $response->getContent();

        // Filtered listings are UI state, not canonical URLs. They
        // keep the bare route as the canonical and emit noindex
        // so Google doesn't index a URL for every combination.
        expect($html)
            ->toContain('<link rel="canonical" href="https://shootingsports.co.za'.$base.'">')
            ->toContain('<meta name="robots" content="noindex,follow">');
    }
});

it('leaves a bare directory index indexable with no filter noindex', function () {
    foreach (['/clubs', '/ranges', '/suppliers'] as $path) {
        $html = $this->get($path)->assertOk()->getContent();

        expect($html)->not->toContain('<meta name="robots" content="noindex');
    }
});

it('returns 404 for a nonsense discipline slug', function () {
    $this->get('/disciplines/no-such-discipline')->assertStatus(404);
    $this->get('/disciplines/no-such-discipline/gauteng')->assertStatus(404);
});

it('leaves opaque token paths unchanged across case folding', function () {
    // Confirm tokens and enquiry threads are case-sensitive opaque
    // values. The canonical middleware must not 301 them to a
    // lowercase variant — that would break the token match.
    $mixed = str_repeat('AbCd1234', 8);

    $confirm = $this->get('/coming-soon/confirm/'.$mixed);
    expect($confirm->status())->not->toBe(301);

    $thread = $this->get('/enquiries/thread/AbCdEf');
    expect($thread->status())->not->toBe(301);
});

it('puts a valid SportsEvent graph on a match page', function () {
    $venue = Venue::factory()->create([
        'slug' => 'crawl-range',
        'name' => 'Crawl Range',
        'town' => 'Centurion',
        'province' => Province::Gauteng,
        'address' => '1 Range Road',
        'status' => ListingStatus::Published,
    ]);
    $host = Organisation::factory()->create([
        'slug' => 'crawl-host',
        'name' => 'Crawl Host',
        'status' => ListingStatus::Published,
    ]);
    $event = Event::factory()->confirmed()->create([
        'slug' => 'crawl-match',
        'title' => 'Crawl Match',
        'venue_id' => $venue->id,
        'host_organisation_id' => $host->id,
        'starts_at' => now()->addWeek()->setTime(9, 0),
        'ends_at' => now()->addWeek()->setTime(13, 0),
    ]);

    $response = $this->get(route('events.show', $event->slug))->assertOk();

    expect($response->getContent())->toMatch('/<script type="application\/ld\+json">(.*?)<\/script>/s');
    preg_match('/<script type="application\/ld\+json">(.*?)<\/script>/s', $response->getContent(), $match);

    $payload = json_decode($match[1], true);

    expect(json_last_error())->toBe(JSON_ERROR_NONE)
        ->and($payload['@context'])->toBe('https://schema.org');

    $node = collect($payload['@graph'])->first(fn (array $item): bool => ($item['@type'] ?? null) === 'SportsEvent');

    expect($node)->toBeArray()
        ->and($node['name'])->toBe('Crawl Match')
        ->and($node['url'])->toBe('https://shootingsports.co.za/events/crawl-match')
        ->and($node['startDate'])->not->toBeEmpty()
        ->and($node['endDate'])->not->toBeEmpty()
        ->and($node['eventStatus'])->toBe('https://schema.org/EventScheduled')
        ->and($node['location']['address']['streetAddress'])->toBe('1 Range Road')
        ->and($node['location']['address']['addressCountry'])->toBe('ZA')
        ->and($node['organizer']['name'])->toBe('Crawl Host');
});

it('ships the IndexNow key file that matches config', function () {
    $key = (string) config('services.indexnow.key');

    expect($key)->not->toBe('')
        ->and(trim((string) file_get_contents(public_path($key.'.txt'))))->toBe($key);
});

it('queues an IndexNow ping when an indexable club is saved and when it is deleted', function () {
    Queue::fake();
    // Disable the debounce for this assertion — with the cooldown on,
    // a create immediately followed by a delete on the same URL only
    // pings once. Behaviour we actually care about: both events
    // trigger a ping path. The debounce is proved in its own test.
    config([
        'services.indexnow.enabled' => true,
        'services.indexnow.key' => 'cc62820955164261944e17736b6d3752',
        'seo.indexnow_cooldown_seconds' => 0,
    ]);

    $club = Organisation::factory()->create([
        'slug' => 'indexnow-club',
        'province' => Province::Gauteng,
        'town' => 'Pretoria',
        'email' => 'club@example.test',
        'status' => ListingStatus::Published,
    ]);

    Queue::assertPushed(PingIndexNow::class, fn (PingIndexNow $job): bool => $job->url === route('clubs.show', 'indexnow-club'));

    $club->delete();

    Queue::assertPushed(PingIndexNow::class, 2);
});

it('debounces repeat IndexNow pings for the same URL inside the cooldown window', function () {
    Queue::fake();
    config([
        'services.indexnow.enabled' => true,
        'services.indexnow.key' => 'cc62820955164261944e17736b6d3752',
        'seo.indexnow_cooldown_seconds' => 3600,
    ]);

    $club = Organisation::factory()->create([
        'slug' => 'debounce-club',
        'province' => Province::Gauteng,
        'town' => 'Pretoria',
        'email' => 'club@example.test',
        'status' => ListingStatus::Published,
    ]);

    Queue::assertPushed(PingIndexNow::class, 1);

    // Editing a public field triggers another ping path; the
    // debounce holds it back because the URL is in cooldown.
    $club->update(['town' => 'Johannesburg']);
    Queue::assertPushed(PingIndexNow::class, 1);

    $club->update(['name' => 'Renamed']);
    Queue::assertPushed(PingIndexNow::class, 1);
});

it('does not ping IndexNow when a save leaves every public field untouched', function () {
    Queue::fake();
    config([
        'services.indexnow.enabled' => true,
        'services.indexnow.key' => 'cc62820955164261944e17736b6d3752',
        'seo.indexnow_cooldown_seconds' => 0,
    ]);

    $club = Organisation::factory()->create([
        'slug' => 'untouched-club',
        'province' => Province::Gauteng,
        'town' => 'Pretoria',
        'email' => 'club@example.test',
        'status' => ListingStatus::Published,
    ]);

    Queue::assertPushed(PingIndexNow::class, 1);

    // Re-save without changing any public-visible field. The observer
    // keeps quiet; Bing is only told about real listing changes.
    $club->save();
    Queue::assertPushed(PingIndexNow::class, 1);
});

it('does not ping IndexNow for a thin club or while the switch is off', function () {
    Queue::fake();
    config(['services.indexnow.enabled' => false]);

    Organisation::factory()->create([
        'province' => Province::Gauteng,
        'town' => 'Pretoria',
        'email' => 'club@example.test',
    ]);

    Queue::assertNothingPushed();

    config(['services.indexnow.enabled' => true]);

    Organisation::factory()->create([
        'province' => null,
        'town' => null,
        'email' => null,
        'phone' => null,
        'website_url' => null,
        'description' => null,
        'status' => ListingStatus::Published,
    ]);

    Queue::assertNothingPushed();
});

it('swallows an IndexNow transport failure', function () {
    config([
        'services.indexnow.key' => 'cc62820955164261944e17736b6d3752',
        'app.url' => 'https://shootingsports.co.za',
    ]);

    Http::fake(function () {
        throw new ConnectionException('timed out');
    });

    expect(fn () => (new PingIndexNow('https://shootingsports.co.za/clubs/indexnow-club'))->handle())
        ->not->toThrow(Throwable::class);
});

it('noindexes the sign-in page', function () {
    $this->get('/login')
        ->assertOk()
        ->assertSee('<meta name="robots" content="noindex, nofollow">', false);
});
