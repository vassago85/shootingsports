<?php

use App\Enums\EventLevel;
use App\Enums\EventStatus;
use App\Enums\OrganisationType;
use App\Models\Event;
use App\Models\Organisation;
use App\Models\Venue;
use Database\Seeders\DisciplineSeeder;
use Database\Seeders\DwarskloofNightShootSeeder;
use Database\Seeders\FreeCivilianShootersSeeder;

it('seeds the Dwarskloof night shoot without duplicating the range', function () {
    $this->seed(DisciplineSeeder::class);
    $this->seed(FreeCivilianShootersSeeder::class);
    $this->seed(DwarskloofNightShootSeeder::class);

    expect(Venue::query()->where('slug', 'dwarskloof-shooting-range')->count())->toBe(1);

    $host = Organisation::query()->where('slug', 'dwarskloof-shooting-range')->first();

    expect($host)->not->toBeNull()
        ->and($host->type)->toBe(OrganisationType::Club);

    $event = Event::query()->where('slug', 'dwarskloof-running-gunning-night-2026')->first();

    expect($event)->not->toBeNull()
        ->and($event->title)->toBe('Running & Gunning Fun Night Shoot')
        ->and($event->starts_at->toDateString())->toBe('2026-09-12')
        ->and($event->all_day)->toBeFalse()
        ->and($event->status)->toBe(EventStatus::Completed)
        ->and($event->level)->toBe(EventLevel::Club)
        ->and($event->entry_fee_cents)->toBe(20000)
        ->and($event->round_count)->toBe(50)
        ->and($event->venue?->slug)->toBe('dwarskloof-shooting-range')
        ->and($event->disciplines->pluck('slug')->all())->toBe(['ipsc-practical']);

    $this->get(route('matches.show', 'dwarskloof-running-gunning-night-2026'))
        ->assertOk()
        ->assertSee('/media/event-banners/dwarskloof-running-gunning-night-2026.png', false);
});
