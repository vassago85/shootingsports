<?php

use App\Enums\EventLevel;
use App\Enums\EventStatus;
use App\Enums\ListingStatus;
use App\Enums\OrganisationType;
use App\Enums\Province;
use App\Models\Event;
use App\Models\Organisation;
use App\Models\Venue;
use Database\Seeders\DisciplineSeeder;
use Database\Seeders\WildmanOpenDay2026Seeder;

it('seeds the Sterkfontein Shooting Academy and the Wildman Open Day', function () {
    $this->seed(DisciplineSeeder::class);
    $this->seed(WildmanOpenDay2026Seeder::class);

    $academy = Organisation::query()->where('slug', 'sterkfontein-shooting-academy')->first();
    expect($academy)->not->toBeNull()
        ->and($academy->type)->toBe(OrganisationType::Club)
        ->and($academy->province)->toBe(Province::Gauteng)
        ->and($academy->town)->toBe('Irene')
        ->and($academy->status)->toBe(ListingStatus::Published)
        ->and($academy->email)->toBe('sales@rosema.co.za')
        ->and($academy->phone)->toBe('082 555 0343')
        ->and($academy->description)->toContain('Irene')
        ->and($academy->disciplines->pluck('slug')->sort()->values()->all())
        ->toBe(['compak-five-stand', 'skeet', 'sporting-clays', 'trap']);

    $venue = Venue::query()->where('slug', 'sterkfontein-shooting-academy')->first();
    expect($venue)->not->toBeNull()
        ->and($venue->province)->toBe(Province::Gauteng)
        ->and($venue->town)->toBe('Irene')
        ->and($venue->status)->toBe(ListingStatus::Published);

    $event = Event::query()->where('slug', 'wildman-sterkfontein-open-day-2026-11-15')->first();
    expect($event)->not->toBeNull()
        ->and($event->title)->toContain('Wildman Open Day')
        ->and($event->starts_at->toDateTimeString())->toBe('2026-11-15 09:00:00')
        ->and($event->all_day)->toBeFalse()
        ->and($event->status)->toBe(EventStatus::EntriesOpen)
        ->and($event->level)->toBe(EventLevel::Club)
        ->and($event->entry_fee_cents)->toBe(80000)
        ->and($event->contact_email)->toBe('sales@rosema.co.za')
        ->and($event->entry_payment_instructions)->toContain('201 851 369')
        ->and($event->entry_payment_instructions)->toContain('Entries close 12 November 2026')
        ->and($event->venue?->slug)->toBe('sterkfontein-shooting-academy')
        ->and($event->disciplines->pluck('slug')->all())->toBe(['compak-five-stand']);
});

it('is idempotent across repeated runs', function () {
    $this->seed(DisciplineSeeder::class);
    $this->seed(WildmanOpenDay2026Seeder::class);
    $this->seed(WildmanOpenDay2026Seeder::class);

    expect(Organisation::query()->where('slug', 'sterkfontein-shooting-academy')->count())->toBe(1)
        ->and(Venue::query()->where('slug', 'sterkfontein-shooting-academy')->count())->toBe(1)
        ->and(Event::query()->where('slug', 'wildman-sterkfontein-open-day-2026-11-15')->count())->toBe(1);
});

it('leaves a staff-enriched academy town untouched on re-seed', function () {
    // Staff open the pending listing in Desk and set the town. A
    // repeat seed must not clobber that curated edit.
    Organisation::factory()->create([
        'slug' => 'sterkfontein-shooting-academy',
        'name' => 'Sterkfontein Shooting Academy',
        'type' => OrganisationType::Club,
        'province' => Province::Gauteng,
        'town' => 'Krugersdorp',
        'status' => ListingStatus::Published,
    ]);

    $this->seed(DisciplineSeeder::class);
    $this->seed(WildmanOpenDay2026Seeder::class);

    $academy = Organisation::query()->where('slug', 'sterkfontein-shooting-academy')->first();

    expect($academy->town)->toBe('Krugersdorp')
        ->and($academy->status)->toBe(ListingStatus::Published);
});
