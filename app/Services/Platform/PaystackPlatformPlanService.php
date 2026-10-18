<?php

namespace App\Services\Platform;

use App\Enums\BillingInterval;
use App\Enums\SubscriptionTier;
use App\Models\PlatformBillingPlan;
use App\Models\PlatformSubscription;
use App\Services\Paystack\PaystackClient;
use App\Support\PlatformFees;
use RuntimeException;

class PaystackPlatformPlanService
{
    public function __construct(private PaystackClient $client) {}

    public function getPlanCode(SubscriptionTier $tier, BillingInterval $interval): string
    {
        $plan = PlatformBillingPlan::query()
            ->where('tier', $tier->value)
            ->where('interval', $interval->value)
            ->first();

        if (! $plan?->paystack_plan_code) {
            throw new RuntimeException("Plan {$tier->value}/{$interval->value} not provisioned on Paystack. Run `php artisan platform:provision-plans`.");
        }

        return $plan->paystack_plan_code;
    }

    /**
     * @return array<string, string> plan codes keyed by "tier.interval"
     */
    public function provisionPlans(): array
    {
        $provisioned = [];

        foreach (SubscriptionTier::cases() as $tier) {
            foreach (BillingInterval::cases() as $interval) {
                $key = "{$tier->value}.{$interval->value}";

                $existingCode = PlatformBillingPlan::query()
                    ->where('tier', $tier->value)
                    ->where('interval', $interval->value)
                    ->value('paystack_plan_code');

                if ($existingCode) {
                    $provisioned[$key] = $existingCode;

                    continue;
                }

                $priceExcl = $tier->priceCents($interval);

                $result = $this->client->createPlan(
                    name: "ShootingSports {$tier->label()} ({$interval->label()})",
                    interval: $interval === BillingInterval::Annual ? 'annually' : 'monthly',
                    amountCents: PlatformFees::vatInclusiveCents($priceExcl),
                );

                PlatformBillingPlan::query()->updateOrCreate(
                    ['tier' => $tier->value, 'interval' => $interval->value],
                    ['price_cents' => $priceExcl, 'paystack_plan_code' => $result['plan_code']],
                );

                $provisioned[$key] = $result['plan_code'];
            }
        }

        return $provisioned;
    }

    public function startSubscription(PlatformSubscription $subscription, string $email, string $callbackUrl): string
    {
        $planCode = $this->getPlanCode($subscription->tier, $subscription->interval);
        $priceIncl = PlatformFees::vatInclusiveCents($subscription->tier->priceCents($subscription->interval));

        $result = $this->client->initializeTransaction(
            email: $email,
            amountCents: $priceIncl,
            planCode: $planCode,
            callbackUrl: $callbackUrl,
            metadata: [
                'purpose' => 'platform_subscription',
                'subscription_id' => $subscription->id,
                'organisation_id' => $subscription->organisation_id,
            ],
        );

        $subscription->update(['paystack_reference' => $result['reference']]);

        return $result['authorization_url'];
    }
}
