<?php

use App\Enums\Plan;
use App\Models\User;
use App\Services\Paystack\SubscriptionApplier;
use Carbon\Carbon;

it('grants Pro for N months without touching Paystack codes', function () {
    Carbon::setTestNow('2026-09-15 12:00:00');

    $user = User::factory()->create(['plan' => Plan::Free]);

    app(SubscriptionApplier::class)->applyManualGrant($user, 3);

    $user->refresh();

    expect($user->plan)->toBe(Plan::Pro)
        ->and($user->plan_billing_cycle)->toBeNull()      // comps do NOT auto-renew
        ->and($user->paystack_subscription_code)->toBeNull()
        ->and($user->paystack_customer_code)->toBeNull()
        ->and($user->plan_expires_at->equalTo(now()->addMonths(3)))->toBeTrue();

    Carbon::setTestNow();
});

it('extends a manual grant from the existing expiry instead of clobbering it', function () {
    Carbon::setTestNow('2026-09-15 12:00:00');

    $user = User::factory()->create([
        'plan' => Plan::Pro,
        'plan_expires_at' => now()->addMonths(2),
    ]);

    app(SubscriptionApplier::class)->applyManualGrant($user, 3);

    $user->refresh();

    // Base was now + 2mo, extended by 3mo → now + 5mo.
    expect($user->plan_expires_at->equalTo(now()->addMonths(5)))->toBeTrue();

    Carbon::setTestNow();
});

it('resubscribing after cancellation clears the cancelled_at flag on renewal', function () {
    $user = User::factory()->create([
        'plan' => Plan::Pro,
        'plan_billing_cycle' => 'monthly',
        'plan_expires_at' => now()->addDays(5),
        'plan_cancelled_at' => now()->subDays(2),
        'paystack_subscription_code' => 'SUB_z',
    ]);

    app(SubscriptionApplier::class)->applyRenewal($user, 'monthly');

    $user->refresh();

    expect($user->plan_cancelled_at)->toBeNull()
        ->and($user->isPro())->toBeTrue()
        ->and($user->hasActiveSubscription())->toBeTrue();
});
