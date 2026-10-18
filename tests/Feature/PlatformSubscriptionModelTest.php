<?php

use App\Enums\BillingInterval;
use App\Enums\PlatformSubscriptionStatus;
use App\Enums\SubscriptionTier;
use App\Models\Organisation;
use App\Models\PlatformSubscription;

it('starts a 30-day trial for a brand-new club', function () {
    config()->set('platform.trial_days', 30);
    $org = Organisation::factory()->create();

    $sub = PlatformSubscription::startTrial($org, SubscriptionTier::Micro, BillingInterval::Monthly);

    expect($sub->organisation_id)->toBe($org->id)
        ->and($sub->tier)->toBe(SubscriptionTier::Micro)
        ->and($sub->status)->toBe(PlatformSubscriptionStatus::Trial)
        ->and($sub->trial_ends_at->isSameDay(now()->addDays(30)))->toBeTrue();
});

it('reports read-only when past due or suspended', function () {
    $org = Organisation::factory()->create();
    $sub = PlatformSubscription::factory()->for($org)->create(['status' => PlatformSubscriptionStatus::PastDue]);

    expect($sub->isReadOnly())->toBeTrue();
});
