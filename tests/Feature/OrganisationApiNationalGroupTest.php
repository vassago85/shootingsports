<?php

use App\Enums\ListingStatus;
use App\Enums\OrganisationType;
use App\Enums\Province;
use App\Models\Organisation;

beforeEach(function (): void {
    $this->withoutVite();
});

it('includes federations in the default organisations response and tags them national', function () {
    Organisation::factory()->create([
        'name' => 'South African Precision Rifle Federation',
        'slug' => 'saprf',
        'type' => OrganisationType::Federation,
        'province' => null,
        'status' => ListingStatus::Published,
    ]);
    Organisation::factory()->create([
        'name' => 'Pretoria Rifle Club',
        'slug' => 'pretoria-rifle-club',
        'type' => OrganisationType::Club,
        'province' => Province::Gauteng,
        'status' => ListingStatus::Published,
    ]);

    $response = $this->getJson('/api/v1/organisations');

    $rows = collect($response->json('data'));

    $federation = $rows->firstWhere('slug', 'saprf');
    expect($federation)->not->toBeNull();
    expect($federation['group'])->toBe('national');

    $club = $rows->firstWhere('slug', 'pretoria-rifle-club');
    expect($club)->not->toBeNull();
    expect($club['group'])->toBe('regional');
});

it('treats province-less associations as national', function () {
    Organisation::factory()->create([
        'name' => 'NRL Hunter South Africa',
        'slug' => 'nrl-hunter-sa',
        'type' => OrganisationType::Association,
        'province' => null,
        'status' => ListingStatus::Published,
    ]);
    Organisation::factory()->create([
        'name' => 'Agri Bo-Kammanassie',
        'slug' => 'agri-bo-kammanassie',
        'type' => OrganisationType::Association,
        'province' => Province::WesternCape,
        'status' => ListingStatus::Published,
    ]);

    $response = $this->getJson('/api/v1/organisations');

    $rows = collect($response->json('data'));

    expect($rows->firstWhere('slug', 'nrl-hunter-sa')['group'])->toBe('national');
    expect($rows->firstWhere('slug', 'agri-bo-kammanassie')['group'])->toBe('regional');
});

it('keeps provincial bodies out of the national group', function () {
    Organisation::factory()->create([
        'name' => 'Central Gauteng Practical Shooting Association',
        'slug' => 'cgpsa',
        'type' => OrganisationType::ProvincialBody,
        'province' => Province::Gauteng,
        'status' => ListingStatus::Published,
    ]);

    $response = $this->getJson('/api/v1/organisations');

    $row = collect($response->json('data'))->firstWhere('slug', 'cgpsa');

    expect($row)->not->toBeNull();
    expect($row['group'])->toBe('regional');
});

it('orders national organisations before regional ones', function () {
    Organisation::factory()->create([
        'name' => 'Alpha Rifle Club',
        'slug' => 'alpha-rifle-club',
        'type' => OrganisationType::Club,
        'province' => Province::Gauteng,
        'status' => ListingStatus::Published,
    ]);
    Organisation::factory()->create([
        'name' => 'Zulu Federation',
        'slug' => 'zulu-federation',
        'type' => OrganisationType::Federation,
        'province' => null,
        'status' => ListingStatus::Published,
    ]);

    $response = $this->getJson('/api/v1/organisations');

    $slugs = collect($response->json('data'))->pluck('slug')->all();
    $federationIndex = array_search('zulu-federation', $slugs, true);
    $clubIndex = array_search('alpha-rifle-club', $slugs, true);

    expect($federationIndex)->toBeLessThan($clubIndex);
});

it('still honours a ?type override', function () {
    Organisation::factory()->create([
        'type' => OrganisationType::Federation,
        'province' => null,
        'status' => ListingStatus::Published,
    ]);
    Organisation::factory()->create([
        'type' => OrganisationType::Club,
        'province' => Province::Gauteng,
        'status' => ListingStatus::Published,
    ]);

    $response = $this->getJson('/api/v1/organisations?type=federation');

    $types = collect($response->json('data'))->pluck('type')->unique()->all();

    expect($types)->toBe(['federation']);
});
