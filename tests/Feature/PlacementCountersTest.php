<?php

use App\Enums\AdPage;
use App\Enums\PlacementSlot;
use App\Models\AdSlot;
use App\Models\Discipline;
use App\Models\Placement;
use App\Models\Provider;

it('counts an impression when a sport page shows the advert', function () {
    $discipline = Discipline::factory()->create();
    $placement = placementFor($discipline);

    $this->get(route('disciplines.show', $discipline))
        ->assertOk()
        ->assertSee(route('placements.click', $placement), false);

    expect($placement->refresh()->impressions)->toBe(1)
        ->and($placement->clicks)->toBe(0);

    $this->get(route('disciplines.show', $discipline))->assertOk();

    expect($placement->refresh()->impressions)->toBe(2);
});

it('does not count an impression for a crawler', function () {
    $discipline = Discipline::factory()->create();
    $placement = placementFor($discipline);

    $this->withHeader('User-Agent', 'Mozilla/5.0 (compatible; Googlebot/2.1; +http://www.google.com/bot.html)')
        ->get(route('disciplines.show', $discipline))
        ->assertOk();

    expect($placement->refresh()->impressions)->toBe(0);
});

it('counts a click and sends the visitor to the advertiser', function () {
    $discipline = Discipline::factory()->create();
    $placement = placementFor($discipline, 'https://whylo.example/k1050');

    $this->get(route('placements.click', $placement))
        ->assertRedirect('https://whylo.example/k1050');

    expect($placement->refresh()->clicks)->toBe(1)
        ->and($placement->impressions)->toBe(0);
});

it('refuses a placement link that is not a web address', function () {
    $discipline = Discipline::factory()->create();
    $placement = placementFor($discipline, 'javascript:alert(1)');

    $this->get(route('placements.click', $placement))->assertNotFound();

    expect($placement->refresh()->clicks)->toBe(0);
});

it('lets a guest follow an advert while the public directory is open behind the gate', function () {
    config([
        'coming-soon.enabled' => true,
        'coming-soon.expose_public' => true,
    ]);

    $discipline = Discipline::factory()->create();
    $placement = placementFor($discipline, 'https://whylo.example/k1050');

    $this->get(route('placements.click', $placement))
        ->assertRedirect('https://whylo.example/k1050');
});

function placementFor(Discipline $discipline, ?string $clickUrl = null): Placement
{
    $provider = Provider::factory()->create([
        'website_url' => 'https://whylo.example',
    ]);

    $slot = AdSlot::query()->create([
        'page' => AdPage::Disciplines,
        'slot' => PlacementSlot::CategorySponsor,
        'name' => 'Discipline sponsor',
        'price_cents' => 0,
        'is_active' => true,
    ]);

    $placement = Placement::query()->create([
        'provider_id' => $provider->id,
        'ad_slot_id' => $slot->id,
        'slot' => PlacementSlot::CategorySponsor,
        'headline' => 'Kahles K1050',
        'click_url' => $clickUrl,
        'starts_on' => now()->subDay()->toDateString(),
        'ends_on' => now()->addMonth()->toDateString(),
        'rate_cents' => 0,
        'is_active' => true,
    ]);

    $placement->disciplines()->sync([$discipline->id]);

    return $placement;
}
