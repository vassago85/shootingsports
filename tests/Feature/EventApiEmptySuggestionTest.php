<?php

use App\Enums\ListingStatus;
use App\Enums\Province;
use App\Models\Discipline;
use App\Models\Event;
use App\Models\Flag;
use App\Models\Organisation;
use App\Models\Venue;
use Database\Seeders\FlagSeeder;

beforeEach(function (): void {
    $this->withoutVite();
    $this->seed(FlagSeeder::class);
});

function makeMatch(array $attrs = [], ?array $flagSlugs = null, ?Discipline $discipline = null): Event
{
    $host = Organisation::factory()->create(['status' => ListingStatus::Published]);
    $venue = Venue::factory()->create([
        'status' => ListingStatus::Published,
        'province' => $attrs['province'] ?? Province::Gauteng,
    ]);

    $event = Event::factory()->confirmed()->create(array_merge([
        'host_organisation_id' => $host->id,
        'venue_id' => $venue->id,
        'starts_at' => now()->addDays(10),
        'ends_at' => now()->addDays(10)->addHours(5),
    ], array_diff_key($attrs, ['province' => true])));

    if ($discipline !== null) {
        $event->disciplines()->attach($discipline->id, ['is_primary' => true]);
    }

    if ($flagSlugs !== null) {
        $event->flags()->sync(
            Flag::query()->whereIn('slug', $flagSlugs)->pluck('id'),
        );
    }

    return $event;
}

it('returns null when the result set is not empty', function () {
    makeMatch();

    $response = $this->getJson('/api/v1/events');

    expect($response->json('meta.suggestion'))->toBeNull();
});

it('returns null for an empty result that has no removable filters', function () {
    // No matches, no filters: the empty state is simply "nothing on
    // the calendar", not "remove X to see Y".
    $response = $this->getJson('/api/v1/events');

    expect($response->json('meta.count'))->toBe(0);
    expect($response->json('meta.suggestion'))->toBeNull();
});

it('suggests removing novice when it would surface matches', function () {
    // Four matches exist. None are flagged beginner-friendly, so a
    // ?novice=true filter returns zero. The server should point at
    // novice as the removal that recovers all four.
    foreach (range(1, 4) as $_) {
        makeMatch();
    }

    $response = $this->getJson('/api/v1/events?novice=true');

    expect($response->json('meta.count'))->toBe(0);
    expect($response->json('meta.suggestion'))->toBe([
        'filter' => 'novice',
        'would_show' => 4,
    ]);
});

it('suggests the filter whose removal exposes the most matches', function () {
    // Three Gauteng matches (none novice) + two Western Cape matches
    // flagged novice. Query asks for Gauteng + novice. Removing
    // novice -> 3 Gauteng matches. Removing province -> 2 novice
    // matches. The server should prefer the removal that returns
    // more rows: drop novice.
    foreach (range(1, 3) as $_) {
        makeMatch(['province' => Province::Gauteng]);
    }
    foreach (range(1, 2) as $_) {
        $event = makeMatch(['province' => Province::WesternCape], ['new-shooter-friendly']);
        // Also set the host and venue province so the province
        // filter actually scopes to WC. The factory already does
        // this via the venue above.
        $event->venue->update(['province' => Province::WesternCape]);
    }

    $response = $this->getJson('/api/v1/events?novice=true&province=gauteng');

    expect($response->json('meta.count'))->toBe(0);
    expect($response->json('meta.suggestion'))->toBe([
        'filter' => 'novice',
        'would_show' => 3,
    ]);
});

it('returns null when no single-filter removal rescues the empty state', function () {
    // Only Gauteng matches exist, none novice. User asks for
    // Western Cape + novice. Removing novice alone still returns 0
    // (no WC matches). Removing province alone also returns 0 (no
    // novice-friendly matches). No single-filter removal helps.
    foreach (range(1, 3) as $_) {
        makeMatch(['province' => Province::Gauteng]);
    }

    $response = $this->getJson('/api/v1/events?novice=true&province=western_cape');

    expect($response->json('meta.count'))->toBe(0);
    expect($response->json('meta.suggestion'))->toBeNull();
});
