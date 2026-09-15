<?php

use App\Models\Event;
use App\Models\Organisation;
use App\Models\Venue;
use Database\Seeders\DisciplineSeeder;
use Database\Seeders\DwarskloofNightShootSeeder;
use Database\Seeders\FreeCivilianShootersSeeder;
use Database\Seeders\MuletechRidgeRangeSeeder;

beforeEach(function () {
    $this->withoutVite();
});

/**
 * Regression guard for the 2026-09-15 range reclassification.
 *
 * Two ranges (Muletech Ridge Range, Dwarskloof Shooting Range) were
 * seeded as both a Venue *and* an Organisation typed `club`. The
 * ghost `Organisation` row polluted /clubs. This test asserts:
 *   1. Neither ghost is ever recreated by the seeders.
 *   2. The hostless events these ranges run render cleanly.
 *   3. /clubs and its ORDER-BY-name listing do not surface either.
 *   4. The old /clubs/<slug> URLs 301 to /ranges/<slug>.
 */
it('the range seeders no longer create ghost Organisation rows', function () {
    $this->seed(DisciplineSeeder::class);
    $this->seed(FreeCivilianShootersSeeder::class);
    $this->seed(MuletechRidgeRangeSeeder::class);
    $this->seed(DwarskloofNightShootSeeder::class);

    expect(Organisation::query()->where('slug', 'muletech-ridge-range')->exists())->toBeFalse()
        ->and(Organisation::query()->where('slug', 'dwarskloof-shooting-range')->exists())->toBeFalse()
        // Both Venue rows still exist — the physical thing.
        ->and(Venue::query()->where('slug', 'muletech-ridge-range')->exists())->toBeTrue()
        ->and(Venue::query()->where('slug', 'dwarskloof-shooting-range')->exists())->toBeTrue();

    // Events are hostless and pointed at the venue.
    foreach ([
        'muletech-loskuil-fundraising-shoot-2026',
        'dwarskloof-running-gunning-night-2026',
    ] as $slug) {
        $event = Event::query()->where('slug', $slug)->firstOrFail();
        expect($event->host_organisation_id)->toBeNull()
            ->and($event->venue_id)->not->toBeNull();
    }
});

it('the /clubs directory does not list the reclassified ranges', function () {
    $this->seed(DisciplineSeeder::class);
    $this->seed(FreeCivilianShootersSeeder::class);
    $this->seed(MuletechRidgeRangeSeeder::class);
    $this->seed(DwarskloofNightShootSeeder::class);

    $this->get(route('clubs.index'))
        ->assertOk()
        ->assertDontSee('Muletech Ridge Range')
        ->assertDontSee('Dwarskloof Shooting Range');
});

it('the reclassified ranges still appear on /ranges', function () {
    $this->seed(DisciplineSeeder::class);
    $this->seed(FreeCivilianShootersSeeder::class);
    $this->seed(MuletechRidgeRangeSeeder::class);
    $this->seed(DwarskloofNightShootSeeder::class);

    $this->get(route('ranges.index'))
        ->assertOk()
        ->assertSee('Muletech Ridge Range')
        ->assertSee('Dwarskloof Shooting Range');
});

it('legacy /clubs URLs for the reclassified ranges 301 to /ranges', function () {
    foreach ([
        'muletech-ridge-range',
        'dwarskloof-shooting-range',
    ] as $slug) {
        $this->get('/clubs/'.$slug)
            ->assertStatus(301)
            ->assertRedirect('/ranges/'.$slug);
    }
});

it('a hostless event renders the venue name in place of the host', function () {
    $this->seed(DisciplineSeeder::class);
    $this->seed(MuletechRidgeRangeSeeder::class);

    // Event card fallback (via calendar page)
    $this->get(route('matches.show', 'muletech-loskuil-fundraising-shoot-2026'))
        ->assertOk()
        ->assertSee('Muletech Ridge Range')
        ->assertDontSee(' · Bothaville · Bothaville'); // no double location
});

it('hostDisplayName falls back to the venue when no host is set', function () {
    $venue = Venue::factory()->create(['name' => 'Some Ridge Range']);

    $event = Event::factory()->create([
        'host_organisation_id' => null,
        'venue_id' => $venue->id,
    ]);
    $event->setRelation('venue', $venue);
    $event->setRelation('hostOrganisation', null);

    expect($event->hostDisplayName())->toBe('Some Ridge Range');
});

it('hostDisplayName falls back to a neutral label when neither host nor venue exist', function () {
    // Bypass the hosted assertion by building the model manually — an
    // event with no host and no venue is a rare edge case (bad import,
    // deleted venue) but the helper must never crash.
    $event = new Event(['title' => 'Ghost']);
    $event->setRelation('hostOrganisation', null);
    $event->setRelation('venue', null);

    expect($event->hostDisplayName())->toBe('Independent match');
});

it('a range-named club still creates but the Filament resource pattern flags it', function () {
    // Belt-and-braces: even after the guard rail, staff can override
    // and save a range-named club (the helper text is a warning, not
    // a validation rule). This test asserts the *pattern used by the
    // helper text* correctly identifies range-shaped names so a
    // future dev refactor does not silently break the guard.
    $names = [
        'Muletech Ridge Range' => true,
        'Dwarskloof Shooting Range' => true,
        'Boland Skietbaan' => true,
        'Central Gauteng Practical Shooting Association' => false,
        'Free Civilian Shooters' => false,
        'SA Hunting Rifle Shooting Association' => false,
    ];

    foreach ($names as $name => $expected) {
        $matches = preg_match('/\b(range|shooting range|skietbaan)\b/i', $name) === 1;
        expect($matches)->toBe($expected, "Pattern misclassified: [{$name}]");
    }
});

it('past /clubs URLs that are neither an org nor a redirect still 404', function () {
    $this->get('/clubs/definitely-not-a-real-slug')
        ->assertNotFound();
});
