<?php

use App\Enums\BillingInterval;
use App\Enums\SubscriptionTier;

it('exposes prices from config for each tier and interval', function () {
    expect(SubscriptionTier::Micro->priceCents(BillingInterval::Monthly))->toBe(10000)
        ->and(SubscriptionTier::Standard->priceCents(BillingInterval::Annual))->toBe(500000)
        ->and(SubscriptionTier::Large->priceCents(BillingInterval::Monthly))->toBe(80000);
});

it('exposes active-member caps per tier', function () {
    expect(SubscriptionTier::Micro->memberCap())->toBe(50)
        ->and(SubscriptionTier::Standard->memberCap())->toBe(250)
        ->and(SubscriptionTier::Large->memberCap())->toBe(1000);
});

it('exposes plain-PHP labels matching getLabel() on tiers and intervals', function () {
    expect(SubscriptionTier::Micro->label())->toBe('Micro')
        ->and(SubscriptionTier::Standard->label())->toBe(SubscriptionTier::Standard->getLabel())
        ->and(SubscriptionTier::Large->label())->toBe('Large')
        ->and(BillingInterval::Monthly->label())->toBe('Monthly')
        ->and(BillingInterval::Annual->label())->toBe(BillingInterval::Annual->getLabel());
});
