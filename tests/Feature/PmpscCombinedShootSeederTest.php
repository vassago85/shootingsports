<?php

use App\Enums\EventStatus;
use App\Enums\OrganisationType;
use App\Models\Event;
use App\Models\Organisation;
use App\Models\Venue;
use Database\Seeders\DisciplineSeeder;
use Database\Seeders\PmpscCombinedShootSeeder;

it('seeds PMPSC, Eeufees, and the 19 September combined shoot', function () {
    $this->seed(DisciplineSeeder::class);
    $this->seed(PmpscCombinedShootSeeder::class);

    $club = Organisation::query()->where('slug', 'pretoria-military-practical-shooting-club')->first();

    expect($club)->not->toBeNull()
        ->and($club->type)->toBe(OrganisationType::Club)
        ->and($club->short_name)->toBe('PMPSC')
        ->and($club->email)->toBe('stats.pmpsc@gmail.com')
        ->and($club->website_url)->toBe('https://www.pmpsc.co.za/')
        ->and($club->logo_path)->toBe('organisation-logos/pmpsc.png');

    $venue = Venue::query()->where('slug', 'eeufees-shooting-range')->first();

    expect($venue)->not->toBeNull()
        ->and($venue->town)->toBe('Pretoria')
        ->and($venue->address)->toBe('Eeufees Road, Pretoria');

    $event = Event::query()->where('slug', 'pmpsc-mpds-fosa-combined-shoot-2026-09-19')->first();

    expect($event)->not->toBeNull()
        ->and($event->host_organisation_id)->toBe($club->id)
        ->and($event->venue_id)->toBe($venue->id)
        ->and($event->status)->toBe(EventStatus::Confirmed)
        ->and($event->starts_at->toDateString())->toBe('2026-09-19')
        ->and($event->entry_fee_cents)->toBe(10000)
        ->and($event->round_count)->toBe(74)
        ->and($event->stage_count)->toBe(5)
        ->and($event->banner_path)->toBe('event-banners/pmpsc-combined-shoot-2026-09-19.png');
});
