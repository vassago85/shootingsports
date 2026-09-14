<?php

use App\Enums\EventLevel;
use App\Enums\EventStatus;
use App\Enums\OrganisationType;
use App\Enums\Province;
use App\Models\Event;
use App\Models\Organisation;
use Database\Seeders\DisciplineSeeder;
use Database\Seeders\SahrsaEventsSeeder;

it('seeds SAHRSA and the Cape Winelands Open from the poster', function () {
    $this->seed(DisciplineSeeder::class);
    $this->seed(SahrsaEventsSeeder::class);

    $host = Organisation::query()->where('slug', 'sahrsa')->first();

    expect($host)->not->toBeNull()
        ->and($host->type)->toBe(OrganisationType::Association)
        ->and($host->website_url)->toBe('https://www.sahuntingrifle.co.za');

    $event = Event::query()->where('slug', 'sahrsa-cape-winelands-open-2026')->first();

    expect($event)->not->toBeNull()
        ->and($event->title)->toBe('Cape Winelands Open')
        ->and($event->starts_at->toDateString())->toBe('2026-10-03')
        ->and($event->status)->toBe(EventStatus::EntriesOpen)
        ->and($event->level)->toBe(EventLevel::Provincial)
        ->and($event->entry_fee_cents)->toBe(60000)
        ->and($event->member_fee_cents)->toBe(55000)
        ->and($event->entry_url)->toBe('https://www.sahuntingrifle.co.za')
        ->and($event->banner_path)->toBe('event-banners/sahrsa-cape-winelands-open-2026.png')
        ->and($event->venue?->name)->toBe('Kinga Distillery')
        ->and($event->venue?->town)->toBe('Montagu')
        ->and($event->venue?->province)->toBe(Province::WesternCape)
        ->and($event->disciplines->pluck('slug')->all())->toBe(['hunting-rifle']);

    $this->get(route('matches.show', 'sahrsa-cape-winelands-open-2026'))
        ->assertOk()
        ->assertSee('/media/event-banners/sahrsa-cape-winelands-open-2026.png', false)
        ->assertSee('Kinga Distillery');
});
