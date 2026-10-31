<?php

use App\Enums\ListingStatus;
use App\Enums\Province;
use App\Jobs\PingIndexNow;
use App\Models\Discipline;
use App\Models\Event;
use App\Models\Organisation;
use App\Models\Venue;
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

it('301s the short aliases to the canonical discipline hubs', function () {
    $this->get('/prs')->assertStatus(301)->assertRedirect('/disciplines/precision-rifle');
    $this->get('/precision-rifle')->assertStatus(301)->assertRedirect('/disciplines/precision-rifle');
    $this->get('/pr22')->assertStatus(301)->assertRedirect('/disciplines/pr22-rimfire');
});

it('renders the hub and province page with the count, date order, and valid JSON-LD', function () {
    [$discipline, $sooner] = hubFixture();

    $hub = $this->get(route('disciplines.show', $discipline->slug));

    $hub->assertOk()
        ->assertSee('<title>Precision Rifle matches in South Africa: 2 upcoming events · Shooting Sports', false)
        ->assertSee('There are 2 upcoming Precision Rifle matches in South Africa.', false)
        ->assertSee('Sooner Hub Match is at Hub Range, Pretoria, Gauteng on 1 Nov 2026.', false)
        ->assertSee('Later Hub Match is at Hub Range, Pretoria, Gauteng on 10 Nov 2026.', false)
        ->assertSee('<strong>Steel</strong>', false)
        ->assertSee('Upcoming matches', false)
        ->assertSee('Where to shoot by province', false)
        ->assertSee('Clubs that run this discipline', false)
        ->assertSee('Ranges that host it', false)
        ->assertSee('Recent results', false)
        ->assertSee('Governing body and links', false);

    $soonerAt = strpos($hub->getContent(), 'Sooner Hub Match is at');
    $laterAt = strpos($hub->getContent(), 'Later Hub Match is at');

    expect($soonerAt)->toBeInt()->toBeLessThan($laterAt);

    $graph = hubJsonLd($hub->getContent());
    $types = collect($graph['@graph'])->pluck('@type');
    $itemList = collect($graph['@graph'])->first(fn (array $node): bool => ($node['@type'] ?? null) === 'ItemList');

    expect($types)->toContain('ItemList')
        ->toContain('BreadcrumbList')
        ->toContain('SportsEvent')
        ->and($itemList['itemListElement'][0]['name'])->toBe('Sooner Hub Match')
        ->and($itemList['itemListElement'][1]['name'])->toBe('Later Hub Match')
        ->and($itemList['numberOfItems'])->toBe(2);

    $this->get(route('disciplines.province', [$discipline->slug, 'gauteng']))
        ->assertOk()
        ->assertSee('<title>Precision Rifle matches in Gauteng: 2 upcoming · Shooting Sports', false)
        ->assertSee('Sooner Hub Match is at Hub Range, Pretoria, Gauteng on 1 Nov 2026.', false);

    $this->get(route('events.show', $sooner->slug))
        ->assertOk()
        ->assertSee(route('disciplines.show', $discipline->slug), false)
        ->assertSee(route('disciplines.province', [$discipline->slug, 'gauteng']), false);
});

it('keeps a province with clubs indexable and drops a fully empty province from the sitemap', function () {
    $discipline = Discipline::factory()->create([
        'slug' => 'precision-rifle',
        'name' => 'Precision Rifle',
        'is_published' => true,
        'intro' => null,
    ]);

    Organisation::factory()->create([
        'name' => 'Hub Limpopo Club',
        'slug' => 'hub-limpopo-club',
        'province' => Province::Limpopo,
        'status' => ListingStatus::Published,
    ])->attachDiscipline($discipline);

    $limpopo = $this->get(route('disciplines.province', [$discipline->slug, 'limpopo']));

    $limpopo->assertOk()
        ->assertSee('Hub Limpopo Club')
        ->assertSee('No upcoming Precision Rifle match is listed in Limpopo.')
        ->assertDontSee('<meta name="robots" content="noindex,follow">', false);

    $empty = $this->get(route('disciplines.province', [$discipline->slug, 'northern-cape']));

    $empty->assertOk()
        ->assertSee('<meta name="robots" content="noindex,follow">', false)
        ->assertSee('No upcoming Precision Rifle match is listed in Northern Cape.');

    PublicCache::forgetSitemaps();

    $xml = $this->get('/sitemaps/disciplines.xml')->assertOk()->getContent();

    expect($xml)->toContain(route('disciplines.province', [$discipline->slug, 'limpopo']))
        ->not->toContain(route('disciplines.province', [$discipline->slug, 'northern-cape']));
});

it('links the homepage and calendar to the PRS and PR22 hubs', function () {
    Discipline::factory()->create([
        'slug' => 'precision-rifle',
        'name' => 'Precision Rifle',
        'is_published' => true,
    ]);
    Discipline::factory()->create([
        'slug' => 'pr22-rimfire',
        'name' => 'PR22 Rimfire',
        'is_published' => true,
    ]);

    $this->get('/')
        ->assertOk()
        ->assertSee('>Precision Rifle matches<', false)
        ->assertSee(route('disciplines.show', 'precision-rifle'), false)
        ->assertSee('>PR22 Rimfire matches<', false);

    $this->get('/calendar')
        ->assertOk()
        ->assertSee(route('disciplines.show', 'pr22-rimfire'), false)
        ->assertSee('>PR22 Rimfire matches<', false);
});

it('pings the discipline hub and province page when an event changes', function () {
    config([
        'services.indexnow.enabled' => true,
        'services.indexnow.key' => 'test-key',
    ]);
    Queue::fake();

    [, $sooner] = hubFixture();

    $sooner->update(['title' => 'Renamed Hub Match']);

    Queue::assertPushed(PingIndexNow::class, fn (PingIndexNow $job): bool => $job->url === route('disciplines.show', 'precision-rifle'));
    Queue::assertPushed(PingIndexNow::class, fn (PingIndexNow $job): bool => $job->url === route('disciplines.province', ['precision-rifle', 'gauteng']));
});

/**
 * @return array{0: Discipline, 1: Event}
 */
function hubFixture(): array
{
    $discipline = Discipline::factory()->create([
        'slug' => 'precision-rifle',
        'name' => 'Precision Rifle',
        'is_published' => true,
        'intro' => '**Steel** at unknown distance.',
    ]);
    $club = Organisation::factory()->create([
        'name' => 'Hub Club',
        'slug' => 'hub-club',
        'status' => ListingStatus::Published,
        'province' => Province::Gauteng,
        'town' => 'Pretoria',
    ]);
    $club->attachDiscipline($discipline);
    $venue = Venue::factory()->create([
        'name' => 'Hub Range',
        'slug' => 'hub-range',
        'province' => Province::Gauteng,
        'town' => 'Pretoria',
        'status' => ListingStatus::Published,
    ]);
    $venue->disciplines()->attach($discipline->id);

    $sooner = Event::factory()->confirmed()->create([
        'title' => 'Sooner Hub Match',
        'slug' => 'sooner-hub-match',
        'host_organisation_id' => $club->id,
        'venue_id' => $venue->id,
        'starts_at' => Carbon::parse('2026-11-01 09:00:00', 'Africa/Johannesburg'),
        'ends_at' => Carbon::parse('2026-11-01 16:00:00', 'Africa/Johannesburg'),
    ]);
    $later = Event::factory()->confirmed()->create([
        'title' => 'Later Hub Match',
        'slug' => 'later-hub-match',
        'host_organisation_id' => $club->id,
        'venue_id' => $venue->id,
        'starts_at' => Carbon::parse('2026-11-10 09:00:00', 'Africa/Johannesburg'),
        'ends_at' => Carbon::parse('2026-11-10 16:00:00', 'Africa/Johannesburg'),
    ]);
    $sooner->syncDisciplines([$discipline->id], $discipline->id);
    $later->syncDisciplines([$discipline->id], $discipline->id);

    return [$discipline, $sooner];
}

function hubJsonLd(string $html): array
{
    preg_match('/<script type="application\/ld\+json">(.*?)<\/script>/s', $html, $matches);

    $decoded = json_decode(html_entity_decode($matches[1] ?? ''), true);

    expect($decoded)->toBeArray()->toHaveKey('@graph');

    return $decoded;
}
