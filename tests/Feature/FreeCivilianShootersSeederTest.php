<?php

use App\Enums\EventLevel;
use App\Enums\EventStatus;
use App\Enums\OrganisationType;
use App\Enums\Province;
use App\Models\Event;
use App\Models\Organisation;
use Database\Seeders\DisciplineSeeder;
use Database\Seeders\FreeCivilianShootersSeeder;

it('seeds Free Civilian Shooters Round 3 from the poster', function () {
    $this->seed(DisciplineSeeder::class);
    $this->seed(FreeCivilianShootersSeeder::class);

    $host = Organisation::query()->where('slug', 'free-civilian-shooters')->first();

    expect($host)->not->toBeNull()
        ->and($host->type)->toBe(OrganisationType::Series)
        ->and($host->phone)->toBe('073 007 5364');

    $event = Event::query()->where('slug', 'free-civilian-round-3-2026')->first();

    expect($event)->not->toBeNull()
        ->and($event->title)->toBe('Round 3 — .22 Rimfire Long Rifle')
        ->and($event->starts_at->toDateString())->toBe('2026-10-17')
        ->and($event->status)->toBe(EventStatus::EntriesOpen)
        ->and($event->level)->toBe(EventLevel::Series)
        ->and($event->entry_fee_cents)->toBe(20000)
        ->and($event->stage_count)->toBe(6)
        ->and($event->round_count)->toBe(50)
        ->and($event->banner_path)->toBe('event-banners/free-civilian-round-3-2026.png')
        ->and($event->venue?->name)->toBe('Dwarskloof Shooting Range')
        ->and($event->venue?->town)->toBe('Randfontein')
        ->and($event->venue?->province)->toBe(Province::Gauteng)
        ->and($event->disciplines->pluck('slug')->all())->toBe(['pr22-rimfire']);

    $this->get(route('matches.show', 'free-civilian-round-3-2026'))
        ->assertOk()
        ->assertSee('/media/event-banners/free-civilian-round-3-2026.png', false)
        ->assertSee('Dwarskloof');
});
