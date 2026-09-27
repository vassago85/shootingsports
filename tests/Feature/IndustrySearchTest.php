<?php

use App\Enums\ListingStatus;
use App\Enums\ProviderCategory;
use App\Enums\Province;
use App\Models\Provider;

beforeEach(function (): void {
    $this->withoutVite();
});

it('filters providers by the search box across name, tagline, town and category', function () {
    Provider::factory()->create([
        'slug' => 'search-test-alpha-arms',
        'name' => 'Alpha Arms Distributors',
        'category' => ProviderCategory::Dealer,
        'status' => ListingStatus::Published,
        'province' => Province::Gauteng,
        'town' => 'Centurion',
    ]);
    Provider::factory()->create([
        'slug' => 'search-test-bravo-optics',
        'name' => 'Bravo Long Range Optics',
        'category' => ProviderCategory::Optics,
        'status' => ListingStatus::Published,
        'province' => Province::WesternCape,
        'town' => 'Cape Town',
    ]);

    $this->get(route('suppliers.index', ['q' => 'Bravo']))
        ->assertOk()
        ->assertSee('Search results for')
        ->assertSee('Bravo Long Range Optics')
        ->assertDontSee('Alpha Arms Distributors');
});

it('offers next actions when nothing matches the industry search', function () {
    Provider::factory()->create([
        'slug' => 'search-test-empty-match',
        'name' => 'Empty Match Placeholder',
        'category' => ProviderCategory::Dealer,
        'status' => ListingStatus::Published,
    ]);

    $this->get(route('suppliers.index', ['q' => 'nonsense-xxxxx']))
        ->assertOk()
        ->assertSee('No suppliers match')
        ->assertSee('Clear search')
        ->assertSee('List a business');
});
