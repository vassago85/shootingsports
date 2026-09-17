<?php

use App\Enums\ListingStatus;
use App\Enums\Province;
use App\Models\Event;
use App\Models\Organisation;
use App\Models\Venue;
use App\Models\VenueAlias;
use App\Services\Venues\VenueMerger;
use App\Services\Venues\VenueResolver;

beforeEach(function (): void {
    $this->withoutVite();
});

// --- Merger writes aliases -----------------------------------------------

it('records loser name and slug on venue_aliases when merged', function () {
    $host = Organisation::factory()->create(['status' => ListingStatus::Published]);

    $primary = Venue::factory()->create([
        'slug' => 'legends-adventure-farm',
        'name' => 'Legends Adventure Farm',
        'province' => Province::Gauteng,
        'status' => ListingStatus::Published,
    ]);

    $dup = Venue::factory()->create([
        'slug' => 'legends-adv-farm',
        'name' => 'Legends Adv Farm',
        'province' => Province::Gauteng,
        'status' => ListingStatus::Published,
    ]);

    Event::factory()->confirmed()->create([
        'host_organisation_id' => $host->id,
        'venue_id' => $dup->id,
    ]);

    $result = app(VenueMerger::class)->merge($primary, [$dup]);

    expect($result['aliases'])->toBeGreaterThanOrEqual(1);

    // Both loser name and slug written against the primary.
    expect(VenueAlias::query()->where('venue_id', $primary->id)->pluck('name')->all())
        ->toContain('Legends Adv Farm');

    expect(VenueAlias::query()->where('slug', 'legends-adv-farm')->value('venue_id'))
        ->toBe($primary->id);
});

it('is idempotent when the same merge runs twice', function () {
    $primary = Venue::factory()->create([
        'name' => 'Papaberg',
        'slug' => 'papaberg-canonical',
        'status' => ListingStatus::Published,
    ]);
    $dup = Venue::factory()->create([
        'name' => 'Papaberg Farm',
        'slug' => 'papaberg-farm',
        'status' => ListingStatus::Published,
    ]);

    app(VenueMerger::class)->merge($primary, [$dup]);

    // A staff re-merge shouldn't blow up on unique constraints.
    // (Revive the loser first — merge() throws if the primary matches
    // the loser or the list is empty.)
    $dup->forceFill(['status' => ListingStatus::Published])->save();
    $result = app(VenueMerger::class)->merge($primary, [$dup]);

    // Single row per loser (name + slug together). The re-merge
    // updates the source stamp but does not spawn a duplicate.
    expect(VenueAlias::query()->where('venue_id', $primary->id)->count())->toBe(1);
    expect($result['aliases'])->toBe(1);
});

// --- Public route 301s onto canonical slug -------------------------------

it('301s /ranges/{old-slug} onto the canonical range URL after a merge', function () {
    $primary = Venue::factory()->create([
        'slug' => 'legends-canonical',
        'name' => 'Legends',
        'status' => ListingStatus::Published,
    ]);
    $dup = Venue::factory()->create([
        'slug' => 'legends-adv-farm-old',
        'name' => 'Legends Adv Farm',
        'status' => ListingStatus::Published,
    ]);

    app(VenueMerger::class)->merge($primary, [$dup]);

    $this->get('/ranges/legends-adv-farm-old')
        ->assertStatus(301)
        ->assertRedirect(route('ranges.show', 'legends-canonical'));
});

it('serves the canonical range URL as 200', function () {
    $venue = Venue::factory()->create([
        'slug' => 'balmoral-farm-x',
        'name' => 'Balmoral Farm',
        'status' => ListingStatus::Published,
    ]);

    $this->get(route('ranges.show', $venue->slug))->assertOk()->assertSee('Balmoral Farm');
});

it('returns 404 for an unknown range slug', function () {
    $this->get('/ranges/never-existed')->assertNotFound();
});

// --- Resolver lookups ----------------------------------------------------

it('resolves an alias name back to the canonical venue for importers', function () {
    $primary = Venue::factory()->create([
        'name' => 'Legends Adventure Farm',
        'status' => ListingStatus::Published,
    ]);
    VenueAlias::query()->create([
        'venue_id' => $primary->id,
        'name' => 'Legends',
        'source' => 'staff',
    ]);

    expect(app(VenueResolver::class)->findByName('Legends')?->id)->toBe($primary->id)
        ->and(app(VenueResolver::class)->findByName('legends')?->id)->toBe($primary->id);
});

it('remembers an incoming name as an alias without duplicating rows', function () {
    $primary = Venue::factory()->create(['name' => 'Rayton', 'status' => ListingStatus::Published]);
    $resolver = app(VenueResolver::class);

    $resolver->rememberAliasName($primary, 'Rayton Range', 'import');
    $resolver->rememberAliasName($primary, 'Rayton Range', 'import');

    expect(VenueAlias::query()->where('venue_id', $primary->id)->count())->toBe(1);
});
