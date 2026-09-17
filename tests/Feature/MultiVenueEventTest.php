<?php

use App\Enums\ListingStatus;
use App\Enums\OrganisationType;
use App\Models\Event;
use App\Models\Organisation;
use App\Models\Venue;
use App\Queries\PublicEventQuery;

beforeEach(function (): void {
    $this->withoutVite();
});

// --- Back-compat: single-venue matches still work ------------------------

it('single-venue matches expose allVenues() as a one-item collection', function () {
    $host = Organisation::factory()->create(['status' => ListingStatus::Published]);
    $venue = Venue::factory()->create(['status' => ListingStatus::Published]);

    $event = Event::factory()->confirmed()->create([
        'host_organisation_id' => $host->id,
        'venue_id' => $venue->id,
    ]);

    expect($event->allVenues()->pluck('id')->all())->toBe([$venue->id]);
});

it('single-venue matches keep their existing location label', function () {
    $host = Organisation::factory()->create(['status' => ListingStatus::Published]);
    $venue = Venue::factory()->create([
        'town' => 'Rayton',
        'status' => ListingStatus::Published,
    ]);
    $event = Event::factory()->confirmed()->create([
        'host_organisation_id' => $host->id,
        'venue_id' => $venue->id,
    ]);

    expect($event->locationLabel())->toContain('Rayton');
});

// --- syncVenues keeps primary in sync ------------------------------------

it('syncVenues writes the pivot and mirrors primary onto events.venue_id', function () {
    $host = Organisation::factory()->create(['status' => ListingStatus::Published]);
    $primary = Venue::factory()->create(['status' => ListingStatus::Published]);
    $secondary = Venue::factory()->create(['status' => ListingStatus::Published]);

    $event = Event::factory()->confirmed()->create([
        'host_organisation_id' => $host->id,
    ]);

    $event->syncVenues([
        ['venue_id' => $primary->id, 'day_label' => 'Day 1'],
        ['venue_id' => $secondary->id, 'day_label' => 'Day 2'],
    ]);

    $event->refresh()->load('venues');

    expect($event->venue_id)->toBe($primary->id)
        ->and($event->venues->pluck('id')->all())->toBe([$primary->id, $secondary->id])
        ->and($event->venues->last()->pivot->day_label)->toBe('Day 2');
});

// --- allVenues prefers pivot over single ---------------------------------

it('allVenues returns every pivot row for a multi-venue match', function () {
    $host = Organisation::factory()->create(['status' => ListingStatus::Published]);
    $a = Venue::factory()->create(['status' => ListingStatus::Published]);
    $b = Venue::factory()->create(['status' => ListingStatus::Published]);
    $c = Venue::factory()->create(['status' => ListingStatus::Published]);

    $event = Event::factory()->confirmed()->create([
        'host_organisation_id' => $host->id,
    ]);
    $event->syncVenues([
        ['venue_id' => $a->id],
        ['venue_id' => $b->id],
        ['venue_id' => $c->id],
    ]);

    expect($event->fresh()->allVenues()->pluck('id')->all())->toBe([$a->id, $b->id, $c->id]);
});

// --- Dual-read filter picks up secondary venues --------------------------

it('venue filter finds a match at a secondary pivot venue as well as the primary', function () {
    $host = Organisation::factory()->create(['status' => ListingStatus::Published]);
    $day1 = Venue::factory()->create(['slug' => 'papaberg-x', 'status' => ListingStatus::Published]);
    $day2 = Venue::factory()->create(['slug' => 'legends-x', 'status' => ListingStatus::Published]);

    $event = Event::factory()->confirmed()->create([
        'host_organisation_id' => $host->id,
        'starts_at' => now()->addWeek(),
    ]);
    $event->syncVenues([
        ['venue_id' => $day1->id, 'day_label' => 'Day 1'],
        ['venue_id' => $day2->id, 'day_label' => 'Day 2'],
    ]);

    // Filter by the *secondary* venue — must still find the match.
    $eventsAtDay2 = (new PublicEventQuery(venueId: $day2->id))->get();

    expect($eventsAtDay2->pluck('id'))->toContain($event->id);

    // Primary venue lookup still works.
    $eventsAtDay1 = (new PublicEventQuery(venueId: $day1->id))->get();

    expect($eventsAtDay1->pluck('id'))->toContain($event->id);
});

// --- Location label for multi-venue --------------------------------------

it('locationLabel summarises how many venues a multi-venue match uses', function () {
    $host = Organisation::factory()->create(['status' => ListingStatus::Published]);
    $a = Venue::factory()->create([
        'town' => 'Rayton',
        'status' => ListingStatus::Published,
    ]);
    $b = Venue::factory()->create(['status' => ListingStatus::Published]);

    $event = Event::factory()->confirmed()->create([
        'host_organisation_id' => $host->id,
    ]);
    $event->syncVenues([
        ['venue_id' => $a->id],
        ['venue_id' => $b->id],
    ]);
    $event->load('venues');

    expect($event->locationLabel())->toContain('+ 1 more');
});

// --- Public detail page lists all venues ---------------------------------

it('renders every venue on the public match detail page for multi-venue matches', function () {
    $host = Organisation::factory()->create([
        'type' => OrganisationType::Club,
        'status' => ListingStatus::Published,
    ]);
    $papaberg = Venue::factory()->create([
        'slug' => 'papaberg-detail',
        'name' => 'Papaberg',
        'status' => ListingStatus::Published,
    ]);
    $legends = Venue::factory()->create([
        'slug' => 'legends-detail',
        'name' => 'Legends Adventure Farm',
        'status' => ListingStatus::Published,
    ]);

    $event = Event::factory()->confirmed()->create([
        'slug' => 'multi-venue-detail-test',
        'host_organisation_id' => $host->id,
    ]);
    $event->syncVenues([
        ['venue_id' => $papaberg->id, 'day_label' => 'Day 1'],
        ['venue_id' => $legends->id, 'day_label' => 'Day 2'],
    ]);

    $this->get(route('matches.show', $event->slug))
        ->assertOk()
        ->assertSee('Papaberg')
        ->assertSee('Legends Adventure Farm')
        ->assertSee('Day 1')
        ->assertSee('Day 2');
});
