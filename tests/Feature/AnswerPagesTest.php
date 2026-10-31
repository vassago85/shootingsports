<?php

use App\Enums\EventStatus;
use App\Enums\ListingStatus;
use App\Enums\Province;
use App\Jobs\PingIndexNow;
use App\Models\Discipline;
use App\Models\Event;
use App\Models\Organisation;
use App\Models\Venue;
use App\Support\DataQualityReport;
use App\Support\PublicCache;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Queue;

beforeEach(function () {
    $this->withoutVite();
    Carbon::setTestNow(Carbon::parse('2026-10-31 08:00:00', 'Africa/Johannesburg'));
});

afterEach(function () {
    Carbon::setTestNow();
});

it('answers this weekend with the date range, the count, and valid JSON-LD', function () {
    [$event] = citationFixture();

    $response = $this->get('/gauteng/this-weekend');

    $response->assertOk()
        ->assertSee('<title>Shooting matches in Gauteng this weekend (31 Oct–1 Nov 2026): 1 event', false)
        ->assertSee('Citation Championship')
        ->assertSee('Citation Club')
        ->assertSee('Citation Range')
        ->assertSee('Pretoria')
        ->assertSee(route('events.show', $event->slug), false);

    $graph = jsonLd($response->getContent());

    expect($graph)->toBeArray()
        ->and(collect($graph['@graph'])->contains(fn (array $node): bool => ($node['@type'] ?? null) === 'ItemList'))->toBeTrue()
        ->and(collect($graph['@graph'])->contains(fn (array $node): bool => ($node['@type'] ?? null) === 'WebSite'))->toBeTrue()
        ->and(collect($graph['@graph'])->contains(fn (array $node): bool => ($node['@type'] ?? null) === 'Organization'))->toBeTrue();

    $website = collect($graph['@graph'])->first(fn (array $node): bool => ($node['@type'] ?? null) === 'WebSite');

    expect($website['potentialAction']['@type'] ?? null)->toBe('SearchAction');
});

it('says when a weekend is empty and links later events', function () {
    [$event, $club] = citationFixture();

    Event::factory()->create([
        'title' => 'Later Citation Match',
        'slug' => 'later-citation-match',
        'host_organisation_id' => $club->id,
        'venue_id' => $event->venue_id,
        'starts_at' => Carbon::parse('2026-11-14 09:00:00', 'Africa/Johannesburg'),
        'all_day' => true,
        'status' => EventStatus::Confirmed,
    ]);

    $this->get('/gauteng/next-weekend')
        ->assertOk()
        ->assertSee('7–8 Nov 2026', false)
        ->assertSee('0 events', false)
        ->assertSee('No shooting matches are listed in Gauteng for 7–8 Nov 2026.')
        ->assertSee('Later Citation Match')
        ->assertSee('Next upcoming events', false)
        ->assertDontSee('Citation Championship');
});

it('redirects a short discipline province URL to the canonical hub', function () {
    citationFixture();

    $this->get('/citation-discipline/gauteng')
        ->assertRedirect(route('disciplines.province', ['citation-discipline', 'gauteng']));

    $this->get(route('disciplines.province', ['citation-discipline', 'gauteng']))
        ->assertOk()
        ->assertSee('Citation Discipline matches in Gauteng: 1 upcoming', false)
        ->assertSee('Citation Championship');

    $this->get(route('disciplines.province', ['citation-discipline', 'limpopo']))
        ->assertOk()
        ->assertSee('name="robots" content="noindex,follow"', false)
        ->assertSee('No upcoming Citation Discipline match is listed in Limpopo.');
});

it('lists answer pages in the sitemap and leaves empty discipline pairs out', function () {
    citationFixture();
    PublicCache::forgetSitemaps();

    $xml = $this->get('/sitemaps/pages.xml')->assertOk()->getContent();

    expect($xml)->toContain(route('answers.this-weekend'))
        ->toContain(route('answers.province-this-weekend', 'gauteng'))
        ->not->toContain(route('answers.discipline', ['citation-discipline', 'gauteng']));

    $disciplines = $this->get('/sitemaps/disciplines.xml')->assertOk()->getContent();

    expect($disciplines)->toContain(route('disciplines.province', ['citation-discipline', 'gauteng']))
        ->not->toContain('/disciplines/citation-discipline/limpopo');
});

it('serves a cached events.json feed with the event fields', function () {
    [$event] = citationFixture();

    $first = $this->get('/events.json');

    $first->assertOk();
    expect($first->headers->get('Cache-Control'))->toContain('public')->toContain('max-age=3600');
    $first
        ->assertJsonPath('events.0.name', 'Citation Championship')
        ->assertJsonPath('events.0.url', $event->publicUrl())
        ->assertJsonPath('events.0.eventStatus', 'https://schema.org/EventScheduled')
        ->assertJsonPath('events.0.offers.price', '150.00');

    expect($first->json('events.0.startDate'))->toContain('+02:00')
        ->and($first->json('events.0.last_updated'))->not->toBeEmpty()
        ->and($first->json('events.0.location.address.addressCountry'))->toBe('ZA');

    $event->update(['title' => 'Renamed Championship']);

    $this->get('/events.json')
        ->assertOk()
        ->assertJsonPath('events.0.name', 'Citation Championship');
});

it('writes the next 60 days into llms-full.txt and points llms.txt at the feeds', function () {
    citationFixture();

    $this->get('/llms-full.txt')
        ->assertOk()
        ->assertHeader('Content-Type', 'text/plain; charset=UTF-8')
        ->assertSee('Shooting Sports is the national register of shooting events, clubs, and ranges in South Africa.')
        ->assertSee('Citation Championship');

    $this->get('/llms.txt')
        ->assertOk()
        ->assertSee('/events.json', false)
        ->assertSee('/llms-full.txt', false)
        ->assertSee('/this-weekend', false)
        ->assertSee('calendar.ics', false);
});

it('puts a followed ShootingSports credit on a club embed', function () {
    [$event, $club] = citationFixture();

    $html = $this->get(route('embed.calendar', ['club' => $club->slug]))
        ->assertOk()
        ->assertSee('Calendar by ShootingSports', false)
        ->assertSee(route('clubs.show', $club->slug), false)
        ->getContent();

    expect($html)->not->toContain('nofollow')
        ->and($html)->toContain($event->title);
});

it('pings IndexNow for the answer pages when they are refreshed', function () {
    Queue::fake();
    config([
        'services.indexnow.enabled' => true,
        'services.indexnow.key' => 'cc62820955164261944e17736b6d3752',
    ]);

    $this->artisan('answers:refresh')->assertSuccessful();

    Queue::assertPushed(PingIndexNow::class, fn (PingIndexNow $job): bool => $job->url === route('answers.this-weekend'));
});

it('flags a Kraaifontein venue stored in Mpumalanga', function () {
    Venue::factory()->create([
        'name' => 'Kraaifontein Range',
        'town' => 'Kraaifontein',
        'province' => Province::Mpumalanga,
        'lat' => null,
        'lng' => null,
    ]);

    $findings = implode("\n", app(DataQualityReport::class)->findings());

    expect($findings)->toContain('Kraaifontein')
        ->and($findings)->toContain('Mpumalanga')
        ->and($findings)->toContain('Western Cape');

    $this->artisan('data-quality:report')
        ->expectsOutputToContain('Kraaifontein')
        ->assertSuccessful();
});

/**
 * @return array{0: Event, 1: Organisation}
 */
function citationFixture(): array
{
    $discipline = Discipline::factory()->create([
        'slug' => 'citation-discipline',
        'name' => 'Citation Discipline',
        'is_published' => true,
    ]);
    $club = Organisation::factory()->create([
        'name' => 'Citation Club',
        'slug' => 'citation-club',
        'status' => ListingStatus::Published,
        'province' => Province::Gauteng,
        'town' => 'Pretoria',
    ]);
    $venue = Venue::factory()->create([
        'name' => 'Citation Range',
        'slug' => 'citation-range',
        'province' => Province::Gauteng,
        'town' => 'Pretoria',
        'address' => '1 Range Road',
        'lat' => -25.7479,
        'lng' => 28.2293,
        'status' => ListingStatus::Published,
    ]);
    $event = Event::factory()->create([
        'title' => 'Citation Championship',
        'slug' => 'citation-championship',
        'host_organisation_id' => $club->id,
        'venue_id' => $venue->id,
        'starts_at' => Carbon::parse('2026-10-31 09:00:00', 'Africa/Johannesburg'),
        'ends_at' => Carbon::parse('2026-10-31 13:00:00', 'Africa/Johannesburg'),
        'all_day' => false,
        'entry_fee_cents' => 15000,
        'status' => EventStatus::Confirmed,
    ]);
    $event->syncDisciplines([$discipline->id], $discipline->id);

    return [$event->fresh(), $club];
}

/**
 * @return array<string, mixed>
 */
function jsonLd(string $html): array
{
    preg_match('/<script type="application\/ld\+json">(.*?)<\/script>/s', $html, $match);

    $decoded = json_decode($match[1] ?? '', true);

    expect($decoded)->toBeArray();

    return $decoded;
}
