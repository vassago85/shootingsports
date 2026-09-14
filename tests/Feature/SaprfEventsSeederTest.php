<?php

use App\Enums\EventStatus;
use App\Enums\OrganisationType;
use App\Models\Event;
use App\Models\Organisation;
use Database\Seeders\DisciplineSeeder;
use Database\Seeders\SaprfEventsSeeder;

it('seeds SAPRF and the upcoming matches from saprf.co.za/events', function () {
    $this->seed(DisciplineSeeder::class);
    $this->seed(SaprfEventsSeeder::class);

    $federation = Organisation::query()->where('slug', 'saprf')->first();

    expect($federation)->not->toBeNull()
        ->and($federation->type)->toBe(OrganisationType::Federation)
        ->and($federation->website_url)->toBe('https://saprf.co.za')
        ->and($federation->logo_path)->toBe('organisation-logos/saprf.png');

    $events = Event::query()->where('host_organisation_id', $federation->id)->orderBy('starts_at')->get();

    expect($events)->toHaveCount(7)
        ->and($events->first()->title)->toBe('Rimfire PR22 MP Provincial')
        ->and($events->first()->starts_at->toDateString())->toBe('2026-10-17')
        ->and($events->first()->status)->toBe(EventStatus::EntriesOpen)
        ->and($events->first()->entry_url)->toBe('https://saprf.co.za/events/111')
        ->and($events->last()->title)->toBe('Centrefire GP 2-Day National Championship Match')
        ->and($events->last()->starts_at->toDateString())->toBe('2026-11-21');
});
