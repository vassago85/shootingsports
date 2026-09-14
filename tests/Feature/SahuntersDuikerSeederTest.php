<?php

use App\Enums\EventLevel;
use App\Enums\EventStatus;
use App\Enums\OrganisationType;
use App\Enums\Province;
use App\Models\Event;
use App\Models\Organisation;
use Database\Seeders\DisciplineSeeder;
use Database\Seeders\SahuntersDuikerSeeder;

it('seeds the Bottari Corolla Challenge from the Duiker poster', function () {
    $this->seed(DisciplineSeeder::class);
    $this->seed(SahuntersDuikerSeeder::class);

    $host = Organisation::query()->where('slug', 'sahunters-duiker')->first();

    expect($host)->not->toBeNull()
        ->and($host->type)->toBe(OrganisationType::Club)
        ->and($host->phone)->toBe('083 278 1739');

    $event = Event::query()->where('slug', 'bottari-corolla-challenge-2026')->first();

    expect($event)->not->toBeNull()
        ->and($event->title)->toBe('Bottari Corolla Challenge')
        ->and($event->starts_at->toDateString())->toBe('2026-09-26')
        ->and($event->status)->toBe(EventStatus::EntriesOpen)
        ->and($event->level)->toBe(EventLevel::Club)
        ->and($event->entry_fee_cents)->toBeNull()
        ->and($event->round_count)->toBe(6)
        ->and($event->banner_path)->toBe('event-banners/bottari-corolla-challenge-2026.png')
        ->and($event->venue?->name)->toBe('Charles Kruger Skietbaan')
        ->and($event->venue?->town)->toBe('Grootvlei')
        ->and($event->venue?->province)->toBe(Province::Mpumalanga)
        ->and($event->disciplines->pluck('slug')->all())->toBe(['hunting-rifle']);

    $this->get(route('matches.show', 'bottari-corolla-challenge-2026'))
        ->assertOk()
        ->assertSee('/media/event-banners/bottari-corolla-challenge-2026.png', false)
        ->assertSee('Charles Kruger');
});
