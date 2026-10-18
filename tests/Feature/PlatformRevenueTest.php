<?php

use App\Enums\BillingInterval;
use App\Enums\SubscriptionTier;
use App\Filament\Pages\PlatformRevenue;
use App\Models\ClubPayable;
use App\Models\PlatformSubscription;
use App\Models\User;

beforeEach(function () {
    $this->withoutVite();
});

it('loads the platform revenue page for a staff user', function () {
    $this->actingAs(User::factory()->staff()->create())
        ->get('/admin/platform-revenue')
        ->assertOk()
        ->assertSee('Platform fee revenue');
});

it('redirects a non-staff user away from the platform revenue page', function () {
    $this->actingAs(User::factory()->create())
        ->get('/admin/platform-revenue')
        ->assertRedirect();
});

it('sums monthly prices plus annual prices divided by twelve for MRR', function () {
    PlatformSubscription::factory()->create([
        'tier' => SubscriptionTier::Micro,
        'interval' => BillingInterval::Monthly,
    ]);
    PlatformSubscription::factory()->create([
        'tier' => SubscriptionTier::Standard,
        'interval' => BillingInterval::Annual,
    ]);
    PlatformSubscription::factory()->trial()->create([
        'tier' => SubscriptionTier::Large,
    ]);

    $tiles = (new PlatformRevenue)->tiles();

    // 10000 monthly Micro + round(500000 / 12) annual Standard; trial excluded.
    expect($tiles['mrr_cents'])->toBe(10000 + 41667)
        ->and($tiles['arr_cents'])->toBe((10000 + 41667) * 12)
        ->and($tiles['active_subscriptions_by_tier'])->toBe(['Micro' => 1, 'Standard' => 1, 'Large' => 0]);
});

it('sums only unpaid club payables for outstanding payables', function () {
    ClubPayable::factory()->create(['net_due_cents' => 10000]);
    ClubPayable::factory()->create(['net_due_cents' => 25000]);
    ClubPayable::factory()->paidOut()->create(['net_due_cents' => 99999]);

    expect((new PlatformRevenue)->tiles()['outstanding_payables_cents'])->toBe(35000);
});
