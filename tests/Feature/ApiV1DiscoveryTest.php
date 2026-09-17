<?php

use App\Enums\DisciplineFamily;
use App\Enums\Province;
use App\Models\Discipline;
use App\Models\Event;
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
