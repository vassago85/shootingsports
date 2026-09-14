<?php

use App\Enums\EventStatus;
use App\Enums\OrganisationType;
use App\Models\Event;
use App\Models\Organisation;
use Database\Seeders\DisciplineSeeder;
use Database\Seeders\SendItElr2026Seeder;

it('seeds the Send It ELR series and 2026 calendar', function () {
    $this->seed(DisciplineSeeder::class);
    $this->seed(SendItElr2026Seeder::class);

    $series = Organisation::query()->where('slug', 'send-it-elr')->first();

    expect($series)->not->toBeNull()
        ->and($series->type)->toBe(OrganisationType::Series)
        ->and($series->name)->toBe('Send It ELR Shooting')
        ->and($series->logo_path)->toBe('organisation-logos/send-it-elr.png');

    $events = Event::query()->where('host_organisation_id', $series->id)->orderBy('starts_at')->get();

    expect($events)->toHaveCount(12)
        ->and($events->every(fn (Event $event) => $event->status === EventStatus::Confirmed))->toBeTrue()
        ->and($events->first()->title)->toBe('1-Mile Challenge Q1')
        ->and($events->first()->starts_at->toDateString())->toBe('2026-02-07')
        ->and($events->last()->title)->toBe('2-Mile Challenge Final')
        ->and($events->last()->starts_at->toDateString())->toBe('2026-11-14');

    $ultra = $events->firstWhere('slug', 'send-it-ultra-2026');

    expect($ultra)->not->toBeNull()
        ->and($ultra->ends_at?->toDateString())->toBe('2026-05-02')
        ->and($ultra->venue?->town)->toBe('Hanover');
});
