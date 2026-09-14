<?php

use App\Enums\EventLevel;
use App\Enums\EventStatus;
use App\Enums\OrganisationType;
use App\Models\Event;
use App\Models\Organisation;
use Database\Seeders\CtsasaEventsSeeder;
use Database\Seeders\DisciplineSeeder;

it('seeds CTSASA and the upcoming 2026 championships from the official calendar', function () {
    $this->seed(DisciplineSeeder::class);
    $this->seed(CtsasaEventsSeeder::class);

    $federation = Organisation::query()->where('slug', 'ctsasa')->first();

    expect($federation)->not->toBeNull()
        ->and($federation->type)->toBe(OrganisationType::Federation)
        ->and($federation->website_url)->toBe('https://ctsasa.co.za')
        ->and($federation->logo_path)->toBe('organisation-logos/ctsasa.png');

    $events = Event::query()->where('host_organisation_id', $federation->id)->orderBy('starts_at')->get();

    expect($events)->toHaveCount(6)
        ->and($events->first()->title)->toBe('SA National English Sporting & FITASC Trap1 Championships')
        ->and($events->first()->starts_at->toDateString())->toBe('2026-09-19')
        ->and($events->first()->status)->toBe(EventStatus::EntriesOpen)
        ->and($events->first()->level)->toBe(EventLevel::National)
        ->and($events->first()->entry_url)->toBe('https://forms.gle/wZUH6G5Efhz2aXBr9')
        ->and($events->first()->venue?->town)->toBe('Nelspruit')
        ->and($events->last()->title)->toBe("Chairman's Cup (Inter-Provincial Championship)")
        ->and($events->last()->starts_at->toDateString())->toBe('2026-11-21')
        ->and($events->last()->status)->toBe(EventStatus::Confirmed)
        ->and($events->last()->venue?->name)->toBe('Wattlespring Sport Shooting Club');

    $gautengNorth = $events->firstWhere('slug', 'ctsasa-gn-english-sporting-trap1-2026');

    expect($gautengNorth)->not->toBeNull()
        ->and($gautengNorth->venue?->name)->toBe('Centurion Gun Club')
        ->and($gautengNorth->disciplines->pluck('slug')->sort()->values()->all())
        ->toBe(['sporting-clays', 'trap']);
});
