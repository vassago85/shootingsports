<?php

use App\Enums\EventStatus;
use App\Enums\ListingStatus;
use App\Enums\Province;
use App\Models\Discipline;
use App\Models\Event;
use App\Models\Organisation;
use App\Models\Venue;

beforeEach(function () {
    $this->withoutVite();
});

it('returns 410 Gone for a soft-deleted match so crawlers drop the URL', function () {
    $event = Event::factory()->confirmed()->create([
        'slug' => 'ghost-match',
        'starts_at' => now()->addWeek(),
    ]);

    $event->delete();

    $response = $this->get(route('events.show', 'ghost-match'));

    $response->assertStatus(410);
    expect($response->headers->get('X-Robots-Tag'))->toContain('noindex');
});

it('returns 410 Gone for a cancelled match', function () {
    $event = Event::factory()->create([
        'slug' => 'cancelled-match',
        'status' => EventStatus::Cancelled,
        'starts_at' => now()->addWeek(),
    ]);

    $this->get(route('events.show', $event->slug))
        ->assertStatus(410);
});

it('returns 404 for an unknown slug', function () {
    $this->get('/events/no-such-match')
        ->assertStatus(404);
});

it('leaves a completed past match indexable with the host details on the page', function () {
    $venue = Venue::factory()->create([
        'slug' => 'completed-range',
        'name' => 'Completed Range',
        'town' => 'Centurion',
        'province' => Province::Gauteng,
        'address' => '1 Range Road',
        'status' => ListingStatus::Published,
    ]);
    $host = Organisation::factory()->create([
        'slug' => 'completed-host',
        'name' => 'Completed Host',
        'town' => 'Pretoria',
        'province' => Province::Gauteng,
        'email' => 'host@example.test',
        'status' => ListingStatus::Published,
    ]);
    $event = Event::factory()->create([
        'slug' => 'completed-match',
        'title' => 'Completed Match',
        'status' => EventStatus::Completed,
        'venue_id' => $venue->id,
        'host_organisation_id' => $host->id,
        'starts_at' => now()->subMonth(),
        'ends_at' => now()->subMonth()->addHours(4),
    ]);

    $response = $this->get(route('events.show', $event->slug));

    $response->assertOk()
        ->assertSee('Completed Match')
        ->assertSee('Completed Range')
        ->assertSee('Completed Host')
        ->assertDontSee('<meta name="robots" content="noindex', false);
});

it('surfaces upcoming matches by the same host club on a past-match page', function () {
    $venue = Venue::factory()->create([
        'slug' => 'next-range',
        'name' => 'Next Range',
        'town' => 'Pretoria',
        'province' => Province::Gauteng,
        'status' => ListingStatus::Published,
    ]);
    $host = Organisation::factory()->create([
        'slug' => 'serial-host',
        'name' => 'Serial Host',
        'town' => 'Pretoria',
        'province' => Province::Gauteng,
        'status' => ListingStatus::Published,
    ]);
    $discipline = Discipline::factory()->create([
        'slug' => 'serial-discipline',
        'name' => 'Serial Discipline',
        'is_published' => true,
    ]);

    $past = Event::factory()->create([
        'slug' => 'past-serial-match',
        'title' => 'Past Serial Match',
        'status' => EventStatus::Completed,
        'host_organisation_id' => $host->id,
        'venue_id' => $venue->id,
        'starts_at' => now()->subMonth(),
    ]);
    $past->syncDisciplines([$discipline->id], $discipline->id);

    $next = Event::factory()->confirmed()->create([
        'slug' => 'next-serial-match',
        'title' => 'Next Serial Match',
        'host_organisation_id' => $host->id,
        'venue_id' => $venue->id,
        'starts_at' => now()->addWeeks(2),
    ]);
    $next->syncDisciplines([$discipline->id], $discipline->id);

    $this->get(route('events.show', $past->slug))
        ->assertOk()
        ->assertSee('Upcoming at Serial Host', false)
        ->assertSee('Next Serial Match');
});
