<?php

use App\Enums\EventStatus;
use App\Models\Event;
use App\Models\Organisation;
use App\Support\EventSpecRows;
use App\Support\Venues\CorrectSendItElrRange;
use Database\Seeders\DisciplineSeeder;
use Database\Seeders\SendItElr2027Seeder;

it('seeds the Send It ELR 2027 dates from the programme sheet', function () {
    $this->seed(DisciplineSeeder::class);
    $this->seed(SendItElr2027Seeder::class);

    $series = Organisation::query()->where('slug', 'send-it-elr')->first();
    $events = Event::query()
        ->where('host_organisation_id', $series->id)
        ->where('slug', 'like', '%2027%')
        ->orderBy('starts_at')
        ->get();

    expect($events)->toHaveCount(9)
        ->and($events->every(fn (Event $event) => $event->status === EventStatus::Confirmed))->toBeTrue()
        ->and($events->map(fn (Event $event) => $event->starts_at->toDateString())->all())->toBe([
            '2027-02-06',
            '2027-03-06',
            '2027-03-13',
            '2027-04-03',
            '2027-04-10',
            '2027-04-29',
            '2027-09-04',
            '2027-10-30',
            '2027-11-13',
        ]);

    $peks = $events->firstWhere('slug', 'send-it-peks-22lr-elr-2027-03-06');
    $peksDistance = collect(EventSpecRows::for($peks->load('disciplines')))
        ->firstWhere(fn (array $row): bool => $row[0] === 'Distance');

    expect($peksDistance[1] ?? null)->toBe('50–750 m');

    $twoMile = $events->firstWhere('slug', 'send-it-2-mile-challenge-2027-03-13');
    $twoMileDistance = collect(EventSpecRows::for($twoMile->load('disciplines')))
        ->firstWhere(fn (array $row): bool => $row[0] === 'Distance');

    expect($twoMileDistance[1] ?? null)->toBe('1 000–3 200 m');

    $ultra = $events->firstWhere('slug', 'send-it-ultra-2027');
    $twoMile = $events->firstWhere('slug', 'send-it-2-mile-challenge-2027-11-13');
    $oneMile = $events->firstWhere('slug', 'send-it-1-mile-challenge-2027-02-06');

    expect($ultra?->ends_at?->toDateString())->toBe('2027-05-01')
        ->and($ultra?->venue?->town)->toBe('Hanover')
        ->and($oneMile?->venue?->name)->toBe('Darling Steel Valley')
        ->and($twoMile?->venue?->slug)->toBe(CorrectSendItElrRange::SLUG)
        ->and($twoMile?->venue?->name)->toBe('Send It ELR 2-Mile Range')
        ->and($twoMile?->description)->toContain('Matroosbergstasie')
        ->and($twoMile?->description)->not->toContain('Skietklub');

    $this->get(route('events.show', 'send-it-2-mile-challenge-2027-03-13'))
        ->assertOk()
        ->assertSee('2-Mile Challenge')
        ->assertSee('Send It ELR 2-Mile Range')
        ->assertDontSee('Matroosbergstasie Skietklub');
});
