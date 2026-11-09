<?php

use App\Enums\EventLevel;
use App\Enums\EventStatus;
use App\Enums\ListingStatus;
use App\Enums\OrganisationType;
use App\Models\Event;
use App\Models\Organisation;
use App\Models\Venue;
use App\Support\Venues\CorrectSendItElrRange;
use Database\Seeders\DisciplineSeeder;
use Database\Seeders\SendItElr2026Seeder;

it('seeds the Send It ELR series and 2026 calendar', function () {
    $this->seed(DisciplineSeeder::class);
    $this->seed(SendItElr2026Seeder::class);

    $series = Organisation::query()->where('slug', 'send-it-elr')->first();

    expect($series)->not->toBeNull()
        ->and($series->type)->toBe(OrganisationType::Series)
        ->and($series->name)->toBe('Send It ELR Shooting')
        ->and($series->description)->toBe('1-mile and 2-mile challenges, .22 ELR, and Send It Ultra. Hosted with APW, 3% Tactical and PEKS.')
        ->and($series->town)->toBeNull()
        ->and($series->logo_path)->toBe('organisation-logos/send-it-elr.png');

    $events = Event::query()->where('host_organisation_id', $series->id)->orderBy('starts_at')->get();

    expect($events)->toHaveCount(12)
        ->and($events->every(fn (Event $event) => $event->status === EventStatus::Confirmed))->toBeTrue()
        ->and($events->every(fn (Event $event) => $event->level === EventLevel::Series))->toBeTrue()
        ->and($events->first()->title)->toBe('1-Mile Challenge Q1')
        ->and($events->first()->starts_at->toDateString())->toBe('2026-02-07')
        ->and($events->last()->title)->toBe('2-Mile Challenge Final')
        ->and($events->last()->starts_at->toDateString())->toBe('2026-11-14');

    $ultra = $events->firstWhere('slug', 'send-it-ultra-2026');

    expect($ultra)->not->toBeNull()
        ->and($ultra->ends_at?->toDateString())->toBe('2026-05-02')
        ->and($ultra->venue?->town)->toBe('Hanover');

    $final = $events->firstWhere('slug', 'send-it-2-mile-challenge-final-2026');

    expect($final?->venue?->slug)->toBe(CorrectSendItElrRange::SLUG)
        ->and($final?->venue?->name)->toBe('Send It ELR 2-Mile Range')
        ->and($final?->venue?->town)->toBe('Matroosbergstasie')
        ->and((float) $final?->venue?->lat)->toEqualWithDelta(-33.42915, 0.00001)
        ->and((float) $final?->venue?->lng)->toEqualWithDelta(19.83748, 0.00001)
        ->and($final?->venue?->address)->toContain('Close to Matroosbergstasie')
        ->and($final?->description)->toContain('Karoo Huisies');
});

it('sends the old Matroosbergstasie range address to the 2-mile range', function () {
    $venue = Venue::factory()->create([
        'slug' => CorrectSendItElrRange::PREVIOUS_SLUG,
        'name' => 'Matroosbergstasie Skietklub',
        'town' => 'Matroosberg',
        'status' => ListingStatus::Published,
    ]);

    Event::factory()->create([
        'slug' => 'send-it-2-mile-challenge-q1-2026',
        'venue_id' => $venue->id,
        'description' => '2-mile challenge at Matroosbergstasie Skietklub.',
    ]);

    app(CorrectSendItElrRange::class)();

    $this->get('/ranges/'.CorrectSendItElrRange::PREVIOUS_SLUG)
        ->assertRedirect(route('ranges.show', CorrectSendItElrRange::SLUG));

    expect($venue->fresh()?->name)->toBe('Send It ELR 2-Mile Range')
        ->and(Event::query()->where('slug', 'send-it-2-mile-challenge-q1-2026')->value('description'))
        ->toContain('Matroosbergstasie');
});
