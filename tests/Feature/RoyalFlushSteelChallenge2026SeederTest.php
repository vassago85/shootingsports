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
use Database\Seeders\RoyalFlushSteelChallenge2026Seeder;

it('seeds the Royal Flush Steel Challenge 2026 series and all eleven matches', function () {
    $this->seed(DisciplineSeeder::class);
    $this->seed(RoyalFlushSteelChallenge2026Seeder::class);

    $series = Organisation::query()->where('slug', 'royal-flush-steel-challenge')->first();

    expect($series)->not->toBeNull()
        ->and($series->type)->toBe(OrganisationType::Series)
        ->and($series->province)->toBe(Province::Gauteng)
        ->and($series->town)->toBe('Rayton')
        ->and($series->facebook_url)->toContain('facebook.com/share/183JfPu4ma')
        ->and($series->status)->toBe(ListingStatus::Published)
        ->and($series->description)->toContain('best three')
        ->and($series->disciplines->pluck('slug')->all())->toBe(['steel-challenge']);

    $venue = Venue::query()->where('slug', 'legends-adventure-farm')->first();
    expect($venue)->not->toBeNull();

    $matches = Event::query()
        ->where('host_organisation_id', $series->id)
        ->orderBy('starts_at')
        ->get();

    expect($matches)->toHaveCount(11);

    expect($matches->pluck('starts_at')->map(fn ($d) => $d->toDateString())->all())->toBe([
        '2026-01-31',
        '2026-02-21',
        '2026-03-28',
        '2026-04-18',
        '2026-05-23',
        '2026-06-27',
        '2026-07-18',
        '2026-08-22',
        '2026-09-05',
        '2026-10-03',
        '2026-11-28',
    ]);

    $final = $matches->last();
    expect($final->title)->toContain('Final')
        ->and($final->description)->toContain('double')
        ->and($final->level)->toBe(EventLevel::Series)
        ->and($final->status)->toBe(EventStatus::Confirmed)
        ->and($final->venue?->slug)->toBe('legends-adventure-farm')
        ->and($final->disciplines->pluck('slug')->all())->toBe(['steel-challenge']);
});

it('is idempotent across repeated runs', function () {
    $this->seed(DisciplineSeeder::class);
    $this->seed(RoyalFlushSteelChallenge2026Seeder::class);
    $this->seed(RoyalFlushSteelChallenge2026Seeder::class);

    expect(Organisation::query()->where('slug', 'royal-flush-steel-challenge')->count())->toBe(1)
        ->and(Venue::query()->where('slug', 'legends-adventure-farm')->count())->toBe(1)
        ->and(Event::query()->where('slug', 'like', 'royal-flush-steel-challenge-%')->count())->toBe(11);
});

it('leaves an already-claimed series facebook URL untouched', function () {
    // A match director edits their series Facebook link in Desk;
    // re-running the seeder must not overwrite the curated value.
    Organisation::factory()->create([
        'slug' => 'royal-flush-steel-challenge',
        'name' => 'Royal Flush Steel Challenge',
        'type' => OrganisationType::Series,
        'facebook_url' => 'https://facebook.com/custom-page-set-by-director',
    ]);

    $this->seed(DisciplineSeeder::class);
    $this->seed(RoyalFlushSteelChallenge2026Seeder::class);

    expect(Organisation::query()->where('slug', 'royal-flush-steel-challenge')->value('facebook_url'))
        ->toBe('https://facebook.com/custom-page-set-by-director');
});
