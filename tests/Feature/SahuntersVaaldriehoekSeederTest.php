<?php

use App\Enums\EventLevel;
use App\Enums\EventStatus;
use App\Enums\OrganisationType;
use App\Enums\Province;
use App\Models\Event;
use App\Models\Organisation;
use Database\Seeders\DisciplineSeeder;
use Database\Seeders\SahuntersVaaldriehoekSeeder;

it('seeds the Vaaldriehoek dangerous-game shoot from the poster', function () {
    $this->seed(DisciplineSeeder::class);
    $this->seed(SahuntersVaaldriehoekSeeder::class);

    $host = Organisation::query()->where('slug', 'sahunters-vaaldriehoek')->first();

    expect($host)->not->toBeNull()
        ->and($host->type)->toBe(OrganisationType::Club);

    $event = Event::query()->where('slug', 'vaaldriehoek-gevaarlike-wild-2026')->first();

    expect($event)->not->toBeNull()
        ->and($event->title)->toBe('Gevaarlike Wild Skiet')
        ->and($event->starts_at->toDateString())->toBe('2026-08-22')
        ->and($event->status)->toBe(EventStatus::Completed)
        ->and($event->level)->toBe(EventLevel::Club)
        ->and($event->entry_fee_cents)->toBe(80000)
        ->and($event->stage_count)->toBe(5)
        ->and($event->round_count)->toBe(20)
        ->and($event->banner_path)->toBe('event-banners/vaaldriehoek-gevaarlike-wild-2026.png')
        ->and($event->venue?->name)->toBe('Koepel')
        ->and($event->venue?->town)->toBe('Parys')
        ->and($event->venue?->province)->toBe(Province::FreeState)
        ->and($event->disciplines->pluck('slug')->all())->toBe(['hunting-rifle']);

    $this->get(route('matches.show', 'vaaldriehoek-gevaarlike-wild-2026'))
        ->assertOk()
        ->assertSee('/media/event-banners/vaaldriehoek-gevaarlike-wild-2026.png', false)
        ->assertSee('Koepel');
});
