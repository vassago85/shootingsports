<?php

use App\Enums\EventLevel;
use App\Enums\EventStatus;
use App\Models\Event;
use App\Models\Organisation;
use App\Models\Venue;
use Database\Seeders\DisciplineSeeder;
use Database\Seeders\DwarskloofNightShootSeeder;
use Database\Seeders\FreeCivilianShootersSeeder;

it('seeds the Dwarskloof night shoot as a hostless range match', function () {
    $this->seed(DisciplineSeeder::class);
    $this->seed(FreeCivilianShootersSeeder::class);
    $this->seed(DwarskloofNightShootSeeder::class);

    expect(Venue::query()->where('slug', 'dwarskloof-shooting-range')->count())->toBe(1);

    // Dwarskloof is a Venue, not a Club — the seeder must not create
    // a matching Organisation row.
    expect(Organisation::query()->where('slug', 'dwarskloof-shooting-range')->exists())->toBeFalse();

    $event = Event::query()->where('slug', 'dwarskloof-running-gunning-night-2026')->first();

    expect($event)->not->toBeNull()
        ->and($event->title)->toBe('Running & Gunning Fun Night Shoot')
        ->and($event->starts_at->toDateString())->toBe('2026-09-12')
        ->and($event->all_day)->toBeFalse()
        ->and($event->status)->toBe(EventStatus::Completed)
        ->and($event->level)->toBe(EventLevel::Club)
        ->and($event->entry_fee_cents)->toBe(20000)
        ->and($event->round_count)->toBe(50)
        ->and($event->host_organisation_id)->toBeNull()
        ->and($event->hostOrganisation)->toBeNull()
        ->and($event->venue?->slug)->toBe('dwarskloof-shooting-range')
        ->and($event->disciplines->pluck('slug')->all())->toBe(['ipsc-practical']);

    $this->get(route('matches.show', 'dwarskloof-running-gunning-night-2026'))
        ->assertOk()
        ->assertSee('/media/event-banners/dwarskloof-running-gunning-night-2026.png', false)
        // Hostless event falls back to venue name on the meta line.
        ->assertSee('Dwarskloof Shooting Range');
});
