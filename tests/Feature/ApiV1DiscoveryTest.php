<?php

use App\Enums\DisciplineFamily;
use App\Enums\FlagFamily;
use App\Enums\Province;
use App\Models\Discipline;
use App\Models\Event;
use App\Models\Flag;
use App\Models\Organisation;
use App\Models\Venue;

it('lists upcoming events as json for the mobile calendar', function (): void {
    $gauteng = Venue::factory()->create(['province' => Province::Gauteng]);
    Event::factory()->confirmed()->create([
        'title' => 'Highveld Steel',
        'venue_id' => $gauteng->id,
    ]);
    Event::factory()->confirmed()->create([
        'title' => 'Boland Gong',
        'venue_id' => Venue::factory()->create(['province' => Province::WesternCape])->id,
    ]);

    $response = $this->getJson('/api/v1/events')
        ->assertOk()
        ->assertJsonStructure([
            'data' => [['id', 'slug', 'title', 'starts_at', 'location_label', 'url', 'venue' => ['slug', 'lat', 'lng']]],
            'meta' => ['count'],
        ]);

    expect(collect($response->json('data'))->pluck('title'))
        ->toContain('Highveld Steel')
        ->toContain('Boland Gong');
});

it('honours the province filter on the events endpoint', function (): void {
    $gauteng = Venue::factory()->create(['province' => Province::Gauteng]);
    $cape = Venue::factory()->create(['province' => Province::WesternCape]);

    Event::factory()->confirmed()->create(['title' => 'Highveld Steel', 'venue_id' => $gauteng->id]);
    Event::factory()->confirmed()->create(['title' => 'Boland Gong', 'venue_id' => $cape->id]);

    $titles = collect($this->getJson('/api/v1/events?province=gauteng')->json('data'))->pluck('title');

    expect($titles)->toContain('Highveld Steel')->not->toContain('Boland Gong');
});

it('honours the venue slug filter so the mobile map can show a single range', function (): void {
    // Two Gauteng venues: tapping the pin for one should NOT bring up
    // the other's matches, even though they share a province. This is
    // the regression the user caught — the map was passing `?province=`
    // instead of `?venue=` and collapsing both back into one list.
    $thabazimbi = Venue::factory()->create([
        'slug' => 'thabazimbi-range',
        'province' => Province::Gauteng,
    ]);
    $wonderboom = Venue::factory()->create([
        'slug' => 'wonderboom-range',
        'province' => Province::Gauteng,
    ]);

    Event::factory()->confirmed()->create([
        'title' => 'Thabazimbi Steel',
        'venue_id' => $thabazimbi->id,
    ]);
    Event::factory()->confirmed()->create([
        'title' => 'Wonderboom Precision',
        'venue_id' => $wonderboom->id,
    ]);

    $titles = collect(
        $this->getJson('/api/v1/events?venue=thabazimbi-range')->json('data'),
    )->pluck('title');

    expect($titles)
        ->toContain('Thabazimbi Steel')
        ->not->toContain('Wonderboom Precision');
});

it('returns event detail with description, venues, and match url', function (): void {
    $event = Event::factory()->confirmed()->create([
        'title' => 'National Precision',
        'description' => 'Two-day rifle match.',
    ]);

    $this->getJson('/api/v1/events/'.$event->slug)
        ->assertOk()
        ->assertJsonPath('data.title', 'National Precision')
        ->assertJsonPath('data.description', 'Two-day rifle match.')
        ->assertJsonStructure(['data' => ['description', 'venues', 'url']]);
});

it('does not expose draft matches through the API', function (): void {
    $draft = Event::factory()->draft()->create(['title' => 'Hidden Draft']);

    $this->getJson('/api/v1/events/'.$draft->slug)->assertNotFound();

    $titles = collect($this->getJson('/api/v1/events')->json('data'))->pluck('title');
    expect($titles)->not->toContain('Hidden Draft');
});

it('returns disciplines with upcoming counts', function (): void {
    Discipline::factory()->create([
        'name' => 'PRS',
        'slug' => 'prs',
        'family' => DisciplineFamily::Rifle,
        'is_published' => true,
        'parent_id' => null,
    ]);

    $data = $this->getJson('/api/v1/disciplines')
        ->assertOk()
        ->assertJsonStructure(['data' => [['id', 'slug', 'name', 'family']]])
        ->json('data');

    expect(collect($data)->pluck('slug'))->toContain('prs');
});

it('returns map pins for pinned venues with upcoming matches', function (): void {
    $venue = Venue::factory()->create([
        'lat' => -26.2041,
        'lng' => 28.0473,
        'province' => Province::Gauteng,
    ]);

    Event::factory()->confirmed()->create(['venue_id' => $venue->id]);

    $this->getJson('/api/v1/map')
        ->assertOk()
        ->assertJsonStructure([
            'pins' => [['slug', 'lat', 'lng', 'count']],
            'centroids' => [['slug', 'label', 'lat', 'lng']],
            'total_matches',
        ]);
});

/*
 * --------------------------------------------------------------------
 * Phase 2 mobile filter tests — Calendar's chip rows depend on these
 * query params. Any backend change to the filter semantics should trip
 * one of these tests before it reaches a user's APK.
 * --------------------------------------------------------------------
 */

it('filters events by discipline family on the API', function (): void {
    $rifle = Discipline::factory()->create([
        'name' => 'PRS',
        'slug' => 'prs',
        'family' => DisciplineFamily::Rifle,
        'is_published' => true,
        'parent_id' => null,
    ]);

    $pistol = Discipline::factory()->create([
        'name' => 'IPSC',
        'slug' => 'ipsc-practical',
        'family' => DisciplineFamily::Handgun,
        'is_published' => true,
        'parent_id' => null,
    ]);

    Event::factory()->confirmed()->create(['title' => 'Rifle Match'])
        ->disciplines()->attach($rifle->id);
    Event::factory()->confirmed()->create(['title' => 'Pistol Match'])
        ->disciplines()->attach($pistol->id);

    $titles = collect($this->getJson('/api/v1/events?family=rifle')->json('data'))->pluck('title');

    expect($titles)->toContain('Rifle Match')->not->toContain('Pistol Match');
});

it('filters events by discipline slug on the API', function (): void {
    $prs = Discipline::factory()->create([
        'name' => 'PRS',
        'slug' => 'prs',
        'family' => DisciplineFamily::Rifle,
        'is_published' => true,
        'parent_id' => null,
    ]);

    $fclass = Discipline::factory()->create([
        'name' => 'F-Class',
        'slug' => 'f-class',
        'family' => DisciplineFamily::Rifle,
        'is_published' => true,
        'parent_id' => null,
    ]);

    Event::factory()->confirmed()->create(['title' => 'National Precision'])
        ->disciplines()->attach($prs->id);
    Event::factory()->confirmed()->create(['title' => 'Bisley F-Class'])
        ->disciplines()->attach($fclass->id);

    $titles = collect($this->getJson('/api/v1/events?discipline=prs')->json('data'))->pluck('title');

    expect($titles)->toContain('National Precision')->not->toContain('Bisley F-Class');
});

it('filters events by the novice flag on the API', function (): void {
    $flag = Flag::query()->create([
        'slug' => 'new-shooter-friendly',
        'name' => 'New shooter friendly',
        'definition' => 'A first-timer can shoot without being a burden on the squad.',
        'family' => FlagFamily::Access,
    ]);

    $noviceEvent = Event::factory()->confirmed()->create(['title' => 'Novice Welcome']);
    $noviceEvent->flags()->attach($flag);

    Event::factory()->confirmed()->create(['title' => 'Experienced Only']);

    $titles = collect($this->getJson('/api/v1/events?novice=1')->json('data'))->pluck('title');

    expect($titles)->toContain('Novice Welcome')->not->toContain('Experienced Only');
});

it('filters events by a from/to date range on the API', function (): void {
    Event::factory()->confirmed()->create([
        'title' => 'This Weekend',
        'starts_at' => now()->addDays(2),
    ]);
    Event::factory()->confirmed()->create([
        'title' => 'Months Away',
        'starts_at' => now()->addMonths(3),
    ]);

    $from = now()->toDateString();
    $to = now()->addDays(7)->toDateString();

    $titles = collect($this->getJson("/api/v1/events?from={$from}&to={$to}")->json('data'))
        ->pluck('title');

    expect($titles)->toContain('This Weekend')->not->toContain('Months Away');
});

it('returns match detail with banner url and host organisation logo', function (): void {
    $organisation = Organisation::factory()->create([
        'logo_path' => 'organisation-logos/example.png',
    ]);

    $event = Event::factory()->confirmed()->create([
        'title' => 'Branded Match',
        'host_organisation_id' => $organisation->id,
    ]);

    $data = $this->getJson('/api/v1/events/'.$event->slug)
        ->assertOk()
        ->assertJsonStructure([
            'data' => [
                'banner_url',
                'host' => [
                    'name',
                    'organisation' => ['slug', 'name', 'short_name', 'logo_url'],
                ],
            ],
        ])
        ->json('data');

    expect($data['host']['organisation']['logo_url'])
        ->toBeString()
        ->toContain('example.png');
});
