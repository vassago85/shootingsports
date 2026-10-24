<?php

use App\Enums\ListingStatus;
use App\Enums\Province;
use App\Models\Event;
use App\Models\Organisation;
use App\Models\Venue;

beforeEach(function (): void {
    $this->withoutVite();
});

it('formats a venue with town and province as "town · code"', function () {
    $venue = Venue::factory()->create([
        'town' => 'Wattlespring',
        'province' => Province::Gauteng,
        'status' => ListingStatus::Published,
    ]);

    $event = Event::factory()->confirmed()->create([
        'venue_id' => $venue->id,
    ]);

    expect($event->locationLabel())->toBe('Wattlespring · GP');
});

it('ignores a town that is actually a province name (importer bug)', function () {
    $venue = Venue::factory()->create([
        'town' => 'Gauteng',
        'province' => Province::Gauteng,
        'status' => ListingStatus::Published,
    ]);

    $event = Event::factory()->confirmed()->create([
        'venue_id' => $venue->id,
    ]);

    expect($event->locationLabel())->toBe('GP');
});

it('ignores a town that matches the lowercase enum value', function () {
    $venue = Venue::factory()->create([
        'town' => 'western_cape',
        'province' => Province::WesternCape,
        'status' => ListingStatus::Published,
    ]);

    $event = Event::factory()->confirmed()->create([
        'venue_id' => $venue->id,
    ]);

    expect($event->locationLabel())->toBe('WC');
});

it('returns "Venue TBC" when the match has no venue', function () {
    $host = Organisation::factory()->create([
        'status' => ListingStatus::Published,
        'town' => 'Pretoria',
        'province' => Province::Gauteng,
    ]);

    $event = Event::factory()->confirmed()->create([
        'host_organisation_id' => $host->id,
        'venue_id' => null,
    ]);

    expect($event->locationLabel())->toBe('Venue TBC');
});

it('never falls back to the host organisation town or province', function () {
    $host = Organisation::factory()->create([
        'status' => ListingStatus::Published,
        'town' => 'Pretoria',
        'province' => Province::Gauteng,
    ]);

    $event = Event::factory()->confirmed()->create([
        'host_organisation_id' => $host->id,
        'venue_id' => null,
    ]);

    expect($event->locationLabel())
        ->not->toContain('Pretoria')
        ->not->toContain('GP');
});

it('shows just the province code when the venue has no town, never the host', function () {
    // Haelgeweer Skietdag regression: the venue record was missing a
    // town but the match's host organisation ("SA Jagters Impala") sat
    // in Pretoria. The card must say "GP", never "Pretoria · GP".
    $host = Organisation::factory()->create([
        'status' => ListingStatus::Published,
        'town' => 'Pretoria',
        'province' => Province::Gauteng,
    ]);

    $venue = Venue::factory()->create([
        'town' => null,
        'province' => Province::Gauteng,
        'status' => ListingStatus::Published,
    ]);

    $event = Event::factory()->confirmed()->create([
        'host_organisation_id' => $host->id,
        'venue_id' => $venue->id,
    ]);

    expect($event->locationLabel())
        ->toBe('GP')
        ->not->toContain('Pretoria');
});

it('returns "Venue TBC" when a venue instance has neither town nor province', function () {
    // Can't be persisted (NOT NULL columns) but the formatter must
    // still degrade gracefully for in-memory instances built from
    // partial data (e.g. from a stale cache or an API fixture).
    $venue = new Venue;
    $venue->town = null;
    $venue->province = null;

    $event = Event::factory()->confirmed()->make();
    $event->setRelation('venue', $venue);

    expect($event->locationLabel())->toBe('Venue TBC');
});
