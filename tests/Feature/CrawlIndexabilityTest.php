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
        expect($body)->not->toContain($agent);
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

it('omits a fake lastmod on static sitemap pages', function () {
    $this->get('/sitemaps/pages.xml')
        ->assertOk()
        ->assertSee('https://shootingsports.co.za', false)
        ->assertDontSee('<lastmod>', false);
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
        ->assertSee('<link rel="canonical" href="https://shootingsports.co.za/calendar">', false);
});

it('keeps a province calendar on its landing canonical', function () {
    $this->get('/calendar?province=gauteng&radius=50&family=rifle')
        ->assertOk()
        ->assertSee('<link rel="canonical" href="https://shootingsports.co.za/calendar?province=gauteng">', false);
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
    config([
        'services.indexnow.enabled' => true,
        'services.indexnow.key' => 'cc62820955164261944e17736b6d3752',
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
