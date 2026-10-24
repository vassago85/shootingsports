<?php

use App\Enums\DisciplineFamily;
use App\Models\Discipline;
use App\Models\Event;

beforeEach(function (): void {
    $this->withoutVite();
});

it('collapses a child discipline onto its parent name', function () {
    $parent = Discipline::factory()->create([
        'slug' => 'precision-rifle',
        'name' => 'Precision Rifle',
        'family' => DisciplineFamily::Rifle,
    ]);

    $child = Discipline::factory()->create([
        'slug' => 'prs',
        'name' => 'PRS',
        'parent_id' => $parent->id,
        'family' => DisciplineFamily::Rifle,
    ]);

    expect($child->displayName())->toBe('Precision Rifle');
    expect($parent->displayName())->toBe('Precision Rifle');
});

it('serializes an event tagged with a child discipline as the parent name', function () {
    $parent = Discipline::factory()->create([
        'slug' => 'precision-rifle',
        'name' => 'Precision Rifle',
        'family' => DisciplineFamily::Rifle,
    ]);

    $child = Discipline::factory()->create([
        'slug' => 'prs',
        'name' => 'PRS',
        'parent_id' => $parent->id,
        'family' => DisciplineFamily::Rifle,
    ]);

    $event = Event::factory()->confirmed()->create();
    $event->disciplines()->attach($child->id, ['is_primary' => true]);

    $row = $this->getJson(route('api.v1.events.show', ['event' => $event->slug]))
        ->assertOk()
        ->json('data');

    $disciplineNames = collect($row['disciplines'])->pluck('name');
    expect($disciplineNames)->toContain('Precision Rifle');
    expect($disciplineNames)->not->toContain('PRS');
});
