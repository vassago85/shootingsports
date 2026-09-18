<?php

use App\Enums\ListingStatus;
use App\Enums\Province;
use App\Models\Discipline;
use App\Models\Event;
use App\Models\Organisation;
use App\Models\User;
use App\Models\Venue;
use Illuminate\Support\Facades\DB;

beforeEach(function () {
    $this->withoutVite();
});

it('noindexes the public site when SEO_INDEXABLE is false and omits the tag when it is true', function () {
    config()->set('seo.indexable', false);
    config()->set('coming-soon.enabled', false);

    $this->get('/')
        ->assertOk()
        ->assertSee('<meta name="robots" content="noindex, nofollow">', false);

    config()->set('seo.indexable', true);

    $this->get('/')
        ->assertOk()
        ->assertDontSee('<meta name="robots" content="noindex, nofollow">', false);
});

it('keeps member routes gated while the public directory is exposed', function () {
    config()->set('coming-soon.enabled', true);
    config()->set('coming-soon.expose_public', false);

    $this->get('/')->assertRedirect(route('coming-soon'));
    $this->get('/register')->assertRedirect(route('coming-soon'));

    config()->set('coming-soon.expose_public', true);

    $this->get('/')->assertOk();
    $this->get('/register')->assertRedirect(route('coming-soon'));

    $this->actingAs(User::factory()->create())
        ->get('/my-calendar')
        ->assertRedirect(route('coming-soon'));
});

it('gives a club a query-matching title, canonical and connected schema', function () {
    $discipline = Discipline::factory()->create(['name' => 'Precision Rifle', 'slug' => 'precision-rifle']);
    $club = Organisation::factory()->create([
        'slug' => 'pretoria-precision',
        'name' => 'Pretoria Precision',
        'town' => 'Pretoria',
        'province' => Province::Gauteng,
        'status' => ListingStatus::Published,
        'email' => 'club@example.test',
    ]);
    $club->attachDiscipline($discipline);

    $this->get(route('clubs.show', $club->slug))
        ->assertOk()
        ->assertSee('Pretoria Precision — Precision Rifle Shooting Club, Pretoria, Gauteng', false)
        ->assertSee('<link rel="canonical" href="'.route('clubs.show', $club->slug).'">', false)
        ->assertSee($club->schemaId(), false)
        ->assertSee('SportsActivityLocation', false)
        ->assertDontSee('<meta name="robots"', false);
});

it('noindexes a thin club and leaves it out of the sitemap', function () {
    config()->set('seo.indexable', true);

    $thin = Organisation::factory()->create([
        'slug' => 'thin-club',
        'name' => 'Thin Club',
        'province' => null,
        'town' => null,
        'email' => null,
        'phone' => null,
        'website_url' => null,
        'description' => null,
        'status' => ListingStatus::Published,
    ]);

    $this->get(route('clubs.show', $thin->slug))
        ->assertOk()
        ->assertSee('<meta name="robots" content="noindex,follow">', false);

    $this->get('/sitemaps/organisations.xml')
        ->assertOk()
        ->assertDontSee('thin-club', false);
});

it('301s a changed club slug and serves the ranking landing on the new path', function () {
    $discipline = Discipline::factory()->create([
        'name' => 'IPSC',
        'slug' => 'ipsc',
    ]);
    $club = Organisation::factory()->create([
        'slug' => 'old-ipsc-club',
        'province' => Province::Gauteng,
        'status' => ListingStatus::Published,
    ]);
    $club->attachDiscipline($discipline);
    $club->update(['slug' => 'new-ipsc-club']);

    $this->get('/clubs/old-ipsc-club')
        ->assertRedirect(route('clubs.show', 'new-ipsc-club'));

    $this->get(route('disciplines.province', ['ipsc', 'gauteng']))
        ->assertRedirect(route('clubs.landing', ['gauteng', 'ipsc']));

    $this->get(route('clubs.landing', ['gauteng', 'ipsc']))
        ->assertOk()
        ->assertSee('IPSC Clubs in Gauteng — Ranges, Matches &amp; Contacts', false);
});

it('puts an ISO offset and venue id on match schema', function () {
    $venue = Venue::factory()->create([
        'slug' => 'schema-range',
        'name' => 'Schema Range',
        'town' => 'Centurion',
        'province' => Province::Gauteng,
        'address' => '1 Range Road',
        'status' => ListingStatus::Published,
    ]);
    $event = Event::factory()->confirmed()->create([
        'slug' => 'schema-match',
        'title' => 'Schema Match',
        'venue_id' => $venue->id,
        'starts_at' => now()->addWeek()->setTime(9, 0),
    ]);

    $this->get(route('matches.show', $event->slug))
        ->assertOk()
        ->assertSee('Schema Match — ', false)
        ->assertSee($event->schemaId(), false)
        ->assertSee($venue->schemaId(), false)
        ->assertSee('+02:00', false)
        ->assertSee('"@type":"SportsEvent"', false);
});

it('serves robots and a schema-valid sitemap in both indexability states', function () {
    config()->set('seo.indexable', false);

    $blocked = $this->get('/robots.txt');
    $blocked->assertOk()->assertSee('Disallow: /', false)->assertSee('Sitemap: '.url('/sitemap.xml'), false);
    expect($blocked->getContent())->not->toContain('Disallow: /admin');

    $hidden = $this->get('/sitemap.xml');
    $hidden->assertOk();
    expect(sitemapRoot($hidden->getContent()))->toBe('sitemapindex');

    $this->get('/sitemaps/pages.xml')
        ->assertOk()
        ->assertDontSee(route('home'), false);

    config()->set('seo.indexable', true);

    $open = $this->get('/robots.txt');
    $open->assertOk()
        ->assertSee('Allow: /', false)
        ->assertSee('Sitemap: '.url('/sitemap.xml'), false);
    expect($open->getContent())
        ->not->toContain('Disallow: /admin')
        ->not->toContain('Disallow: /desk')
        ->not->toContain('Disallow: /my-calendar');

    $index = $this->get('/sitemap.xml')->assertOk();
    expect(sitemapRoot($index->getContent()))->toBe('sitemapindex');

    $pages = $this->get('/sitemaps/pages.xml')->assertOk();
    expect(sitemapRoot($pages->getContent()))->toBe('urlset');
    $pages->assertSee(route('home'), false);
});

it('chunks a sitemap type once it passes the configured limit', function () {
    config()->set('seo.sitemap_chunk', 1);

    Organisation::factory()->create(['slug' => 'chunk-club-a', 'status' => ListingStatus::Published]);
    Organisation::factory()->create(['slug' => 'chunk-club-b', 'status' => ListingStatus::Published]);

    $this->get('/sitemap.xml')
        ->assertOk()
        ->assertSee('/sitemaps/organisations-1.xml', false)
        ->assertSee('/sitemaps/organisations-2.xml', false);

    expect(sitemapRoot($this->get('/sitemaps/organisations-1.xml')->assertOk()->getContent()))->toBe('urlset');
});

it('does not add a query per club on a province landing', function () {
    $discipline = Discipline::factory()->create(['slug' => 'query-discipline', 'name' => 'Query Discipline']);

    Organisation::factory()->create([
        'slug' => 'query-club-1',
        'province' => Province::Gauteng,
        'status' => ListingStatus::Published,
    ])->attachDiscipline($discipline);

    DB::flushQueryLog();
    DB::enableQueryLog();
    $this->get(route('clubs.landing', ['gauteng', $discipline->slug]))->assertOk();
    $few = count(DB::getQueryLog());

    foreach (range(2, 6) as $i) {
        Organisation::factory()->create([
            'slug' => 'query-club-'.$i,
            'province' => Province::Gauteng,
            'status' => ListingStatus::Published,
        ])->attachDiscipline($discipline);
    }

    DB::flushQueryLog();
    $this->get(route('clubs.landing', ['gauteng', $discipline->slug]))->assertOk();
    $many = count(DB::getQueryLog());

    expect($many)->toBeLessThanOrEqual($few + 2);
});

it('warms the sitemap cache', function () {
    $this->artisan('sitemap:warm')->assertSuccessful();
});

function sitemapRoot(string $xml): string
{
    $dom = new DOMDocument;
    expect($dom->loadXML($xml))->toBeTrue()
        ->and($dom->documentElement->namespaceURI)->toBe('http://www.sitemaps.org/schemas/sitemap/0.9');

    return $dom->documentElement->localName;
}
