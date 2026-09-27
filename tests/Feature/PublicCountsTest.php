<?php

use App\Enums\ListingStatus;
use App\Enums\OrganisationType;
use App\Enums\ProviderCategory;
use App\Models\Discipline;
use App\Models\Organisation;
use App\Models\Provider;
use App\Models\Venue;
use App\Support\PublicCache;
use App\Support\PublicCounts;
use Illuminate\Support\Facades\Cache;

beforeEach(function (): void {
    $this->withoutVite();
    Cache::forget(PublicCache::key('stats'));
});

it('reports the delta after new published listings are added', function () {
    Cache::forget(PublicCache::key('stats'));
    $baseline = PublicCounts::all();

    Organisation::factory()->count(2)->create(['type' => OrganisationType::Club, 'status' => ListingStatus::Published]);
    Organisation::factory()->create(['type' => OrganisationType::Series, 'status' => ListingStatus::Published]);
    Venue::factory()->count(3)->create(['status' => ListingStatus::Published]);
    Discipline::factory()->count(2)->create(['is_published' => true]);
    Provider::factory()->count(4)->create([
        'status' => ListingStatus::Published,
        'category' => ProviderCategory::Dealer,
    ]);

    Cache::forget(PublicCache::key('stats'));
    $counts = PublicCounts::all();

    // Provinces is a constant; every other count increments by the
    // number of published fixtures we added.
    expect($counts['clubs'] - $baseline['clubs'])->toBe(2)
        ->and($counts['series'] - $baseline['series'])->toBe(1)
        ->and($counts['ranges'] - $baseline['ranges'])->toBe(3)
        ->and($counts['disciplines'] - $baseline['disciplines'])->toBe(2)
        ->and($counts['suppliers'] - $baseline['suppliers'])->toBe(4)
        ->and($counts['provinces'])->toBe(9);
});

it('reflects new listings after PublicCache is bumped', function () {
    Cache::forget(PublicCache::key('stats'));
    $before = PublicCounts::get('clubs');

    Organisation::factory()->create(['type' => OrganisationType::Club, 'status' => ListingStatus::Published]);
    PublicCache::bump();

    expect(PublicCounts::get('clubs'))->toBe($before + 1);
});
