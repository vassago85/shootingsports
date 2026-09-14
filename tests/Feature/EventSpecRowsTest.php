<?php

use App\Enums\DisciplineFamily;
use App\Models\Discipline;
use App\Models\Event;
use App\Support\EventSpecRows;

it('uses distance and rounds for precision rifle cards', function () {
    $discipline = Discipline::factory()->create([
        'slug' => 'prs',
        'name' => 'PRS',
        'family' => DisciplineFamily::Rifle,
        'typical_distances' => '300–1 000 m',
    ]);
    $event = Event::factory()->confirmed()->create([
        'round_count' => 92,
        'entry_fee_cents' => 45000,
    ]);
    $event->syncDisciplines([$discipline->id], $discipline->id);
    $event->load('disciplines');

    $rows = collect(EventSpecRows::for($event->fresh(['disciplines'])));

    expect($rows->firstWhere(fn ($row) => $row[0] === 'Distance')[1])->toBe('300–1 000 m')
        ->and($rows->firstWhere(fn ($row) => $row[0] === 'Rounds')[1])->toBe('92')
        ->and($rows->firstWhere(fn ($row) => $row[0] === 'Entry')[1])->toBe('R450');
});

it('uses stages and min rounds for IPSC cards', function () {
    $discipline = Discipline::factory()->create([
        'slug' => 'ipsc-practical',
        'name' => 'IPSC Practical',
        'family' => DisciplineFamily::Handgun,
    ]);
    $event = Event::factory()->confirmed()->create([
        'stage_count' => 8,
        'round_count' => 140,
    ]);
    $event->syncDisciplines([$discipline->id], $discipline->id);

    $rows = collect(EventSpecRows::for($event->fresh(['disciplines'])));

    expect($rows->firstWhere(fn ($row) => $row[0] === 'Stages')[1])->toBe('8')
        ->and($rows->firstWhere(fn ($row) => $row[0] === 'Min rounds')[1])->toBe('140')
        ->and($rows->firstWhere(fn ($row) => $row[0] === 'Distance'))->toBeNull();
});

it('uses targets and stands for clay cards', function () {
    $discipline = Discipline::factory()->create([
        'slug' => 'sporting-clays',
        'name' => 'Sporting Clays',
        'family' => DisciplineFamily::Shotgun,
    ]);
    $event = Event::factory()->confirmed()->create([
        'target_count' => 100,
        'stage_count' => 10,
    ]);
    $event->syncDisciplines([$discipline->id], $discipline->id);

    $rows = collect(EventSpecRows::for($event->fresh(['disciplines'])));

    expect($rows->firstWhere(fn ($row) => $row[0] === 'Targets')[1])->toBe('100')
        ->and($rows->firstWhere(fn ($row) => $row[0] === 'Stands')[1])->toBe('10');
});

it('hides empty spec rows', function () {
    $event = Event::factory()->confirmed()->create([
        'round_count' => null,
        'entry_fee_cents' => null,
        'venue_id' => null,
    ]);

    $labels = collect(EventSpecRows::for($event->fresh(['disciplines'])))->pluck(0);

    expect($labels)->toContain('Venue')
        ->not->toContain('Entry')
        ->not->toContain('Rounds');
});
