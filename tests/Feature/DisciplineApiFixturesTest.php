<?php

use App\Enums\DisciplineFamily;
use App\Models\Discipline;

beforeEach(function (): void {
    $this->withoutVite();
});

it('hides verify-% test fixtures from the public discipline API', function () {
    Discipline::factory()->create([
        'name' => 'Precision Rifle',
        'slug' => 'precision-rifle',
        'family' => DisciplineFamily::Rifle,
        'is_published' => true,
    ]);

    Discipline::factory()->create([
        'name' => 'Verify Disc',
        'slug' => 'verify-disc-abcdef',
        'family' => DisciplineFamily::Rifle,
        'is_published' => true,
    ]);

    $response = $this->getJson('/api/v1/disciplines');

    $slugs = collect($response->json('data'))->pluck('slug')->all();

    expect($slugs)
        ->toContain('precision-rifle')
        ->not->toContain('verify-disc-abcdef');
});

it('lists PR22 Rimfire as a top-level rifle discipline', function () {
    // PR22 Rimfire was previously nested under Precision Rifle
    // (parent_id = 2) and never appeared in the sheet. The migration
    // promotes it; this guards against a regression where it slides
    // back under a parent.
    Discipline::factory()->create([
        'name' => 'Precision Rifle',
        'slug' => 'precision-rifle',
        'family' => DisciplineFamily::Rifle,
        'is_published' => true,
    ]);
    Discipline::factory()->create([
        'name' => 'PR22 Rimfire',
        'slug' => 'pr22-rimfire',
        'family' => DisciplineFamily::Rifle,
        'parent_id' => null,
        'is_published' => true,
    ]);

    $response = $this->getJson('/api/v1/disciplines');

    $slugs = collect($response->json('data'))->pluck('slug')->all();

    expect($slugs)->toContain('pr22-rimfire');
});
