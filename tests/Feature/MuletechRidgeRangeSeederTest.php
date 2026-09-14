<?php

use App\Enums\EventLevel;
use App\Enums\EventStatus;
use App\Enums\OrganisationType;
use App\Enums\Province;
use App\Models\Event;
use App\Models\Organisation;
use Database\Seeders\DisciplineSeeder;
use Database\Seeders\MuletechRidgeRangeSeeder;

it('seeds the Muletech Ridge Range Loskuil fundraiser from the poster', function () {
    $this->seed(DisciplineSeeder::class);
    $this->seed(MuletechRidgeRangeSeeder::class);

    $host = Organisation::query()->where('slug', 'muletech-ridge-range')->first();

    expect($host)->not->toBeNull()
        ->and($host->type)->toBe(OrganisationType::Club)
        ->and($host->phone)->toBe('073 551 6065');

    $event = Event::query()->where('slug', 'muletech-loskuil-fundraising-shoot-2026')->first();

    expect($event)->not->toBeNull()
        ->and($event->title)->toBe('Fundraising Shooting Day')
        ->and($event->starts_at->toDateString())->toBe('2026-11-14')
        ->and($event->status)->toBe(EventStatus::EntriesOpen)
        ->and($event->level)->toBe(EventLevel::Club)
        ->and($event->entry_fee_cents)->toBe(45000)
        ->and($event->stage_count)->toBe(7)
        ->and($event->round_count)->toBe(50)
        ->and($event->banner_path)->toBe('event-banners/muletech-loskuil-fundraising-shoot-2026.png')
        ->and($event->venue?->name)->toBe('Muletech Ridge Range')
        ->and($event->venue?->town)->toBe('Bothaville')
        ->and($event->venue?->province)->toBe(Province::FreeState)
        ->and($event->disciplines->pluck('slug')->all())->toBe(['precision-rifle']);

    $this->get(route('matches.show', 'muletech-loskuil-fundraising-shoot-2026'))
        ->assertOk()
        ->assertSee('/media/event-banners/muletech-loskuil-fundraising-shoot-2026.png', false)
        ->assertSee('Loskuil Primary');
});
