<?php

use App\Enums\EventLevel;
use App\Enums\EventStatus;
use App\Enums\Province;
use App\Models\Event;
use App\Models\Organisation;
use App\Models\Venue;
use Database\Seeders\DisciplineSeeder;
use Database\Seeders\MuletechRidgeRangeSeeder;

it('seeds the Muletech Ridge Range Loskuil fundraiser from the poster', function () {
    $this->seed(DisciplineSeeder::class);
    $this->seed(MuletechRidgeRangeSeeder::class);

    // Muletech is a Venue, not a Club — the seeder must not create a
    // matching Organisation row (the reclassification cleanup depends
    // on the seeder never re-creating the ghost).
    expect(Organisation::query()->where('slug', 'muletech-ridge-range')->exists())->toBeFalse();

    $venue = Venue::query()->where('slug', 'muletech-ridge-range')->first();

    expect($venue)->not->toBeNull()
        ->and($venue->name)->toBe('Muletech Ridge Range')
        ->and($venue->town)->toBe('Bothaville')
        ->and($venue->province)->toBe(Province::FreeState);

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
        // Range operator hosts the match — no external club.
        ->and($event->host_organisation_id)->toBeNull()
        ->and($event->hostOrganisation)->toBeNull()
        ->and($event->venue?->name)->toBe('Muletech Ridge Range')
        ->and($event->disciplines->pluck('slug')->all())->toBe(['precision-rifle']);

    $this->get(route('matches.show', 'muletech-loskuil-fundraising-shoot-2026'))
        ->assertOk()
        ->assertSee('/media/event-banners/muletech-loskuil-fundraising-shoot-2026.png', false)
        ->assertSee('Loskuil Primary')
        // Hostless event falls back to venue name on the meta line.
        ->assertSee('Muletech Ridge Range');
});
