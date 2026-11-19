<?php

use App\Enums\EventLevel;
use App\Enums\EventStatus;
use App\Enums\OrganisationType;
use App\Enums\Province;
use App\Models\Event;
use App\Models\Organisation;
use App\Models\Venue;
use Database\Seeders\DisciplineSeeder;
use Database\Seeders\PprcDecember2026Seeder;

it('seeds the PPRC year-end rimfire and centerfire matches on 5 December 2026', function () {
    $this->seed(DisciplineSeeder::class);
    $this->seed(PprcDecember2026Seeder::class);

    $pprc = Organisation::query()->where('slug', 'pretoria-precision-rifle-club')->first();

    expect($pprc)->not->toBeNull()
        ->and($pprc->type)->toBe(OrganisationType::Club)
        ->and($pprc->province)->toBe(Province::Gauteng)
        ->and($pprc->town)->toBe('Pretoria')
        ->and($pprc->website_url)->toBe('https://pretoriaprc.co.za')
        ->and($pprc->disciplines->pluck('slug')->sort()->values()->all())->toBe(['pr22-rimfire', 'prs']);

    $pappaburg = Venue::query()->where('slug', 'pappaburg')->first();
    $legends = Venue::query()->where('slug', 'legends-adventure-farm')->first();

    expect($pappaburg)->not->toBeNull()
        ->and($pappaburg->province)->toBe(Province::Gauteng)
        ->and($legends)->not->toBeNull()
        ->and($legends->province)->toBe(Province::Gauteng);

    $rimfire = Event::query()->where('slug', 'pprc-rimfire-club-match-year-end-2026-12-05')->first();
    $centerfire = Event::query()->where('slug', 'pprc-centerfire-year-end-match-2026-12-05')->first();

    expect($rimfire)->not->toBeNull()
        ->and($rimfire->title)->toBe('PPRC Rimfire Club Match year end')
        ->and($rimfire->starts_at->toDateString())->toBe('2026-12-05')
        ->and($rimfire->level)->toBe(EventLevel::Club)
        ->and($rimfire->status)->toBe(EventStatus::Confirmed)
        ->and($rimfire->host_organisation_id)->toBe($pprc->id)
        ->and($rimfire->venue?->slug)->toBe('pappaburg')
        ->and($rimfire->disciplines->pluck('slug')->all())->toBe(['pr22-rimfire']);

    expect($centerfire)->not->toBeNull()
        ->and($centerfire->title)->toBe('PPRC Center Fire Year End Match')
        ->and($centerfire->starts_at->toDateString())->toBe('2026-12-05')
        ->and($centerfire->level)->toBe(EventLevel::Club)
        ->and($centerfire->status)->toBe(EventStatus::Confirmed)
        ->and($centerfire->host_organisation_id)->toBe($pprc->id)
        ->and($centerfire->venue?->slug)->toBe('legends-adventure-farm')
        ->and($centerfire->disciplines->pluck('slug')->all())->toBe(['prs']);
});

it('is idempotent across repeated runs', function () {
    $this->seed(DisciplineSeeder::class);
    $this->seed(PprcDecember2026Seeder::class);
    $this->seed(PprcDecember2026Seeder::class);

    expect(Organisation::query()->where('slug', 'pretoria-precision-rifle-club')->count())->toBe(1)
        ->and(Venue::query()->where('slug', 'pappaburg')->count())->toBe(1)
        ->and(Venue::query()->where('slug', 'legends-adventure-farm')->count())->toBe(1)
        ->and(Event::query()->whereIn('slug', [
            'pprc-rimfire-club-match-year-end-2026-12-05',
            'pprc-centerfire-year-end-match-2026-12-05',
        ])->count())->toBe(2);
});

it('leaves a manually curated PPRC description untouched', function () {
    // The club has already claimed its listing on shootingsports.co.za
    // and set a custom description; a later deploy must not overwrite it.
    Organisation::factory()->create([
        'slug' => 'pretoria-precision-rifle-club',
        'name' => 'Pretoria Precision Rifle Club',
        'type' => OrganisationType::Club,
        'description' => 'Custom description set by the match director.',
    ]);

    $this->seed(DisciplineSeeder::class);
    $this->seed(PprcDecember2026Seeder::class);

    expect(Organisation::query()->where('slug', 'pretoria-precision-rifle-club')->value('description'))
        ->toBe('Custom description set by the match director.');
});
