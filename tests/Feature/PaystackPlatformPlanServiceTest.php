<?php

use App\Enums\BillingInterval;
use App\Enums\SubscriptionTier;
use App\Models\PlatformBillingPlan;
use App\Models\PlatformSubscription;
use App\Services\Platform\PaystackPlatformPlanService;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    config()->set('services.paystack.secret_key', 'sk_test_dummy');
});

it('provisions all 6 plans on Paystack and caches the plan codes', function () {
    Http::fake(function ($request) {
        if ($request->method() === 'GET') {
            return Http::response(['status' => true, 'data' => []], 200);
        }

        $body = $request->data();

        return Http::response([
            'status' => true,
            'message' => 'Plan created',
            'data' => [
                'plan_code' => 'PLN_'.substr(sha1($body['name']), 0, 10),
                'name' => $body['name'],
                'interval' => $body['interval'],
                'amount' => $body['amount'],
            ],
        ], 200);
    });

    app(PaystackPlatformPlanService::class)->provisionPlans();

    expect(PlatformBillingPlan::count())->toBe(6);
    foreach (SubscriptionTier::cases() as $tier) {
        foreach (BillingInterval::cases() as $interval) {
            $plan = PlatformBillingPlan::query()
                ->where('tier', $tier->value)
                ->where('interval', $interval->value)
                ->firstOrFail();
            expect($plan->paystack_plan_code)->toStartWith('PLN_');
        }
    }
});

it('is idempotent — a second provisionPlans run keeps the local records unchanged', function () {
    Http::fake(function ($request) {
        // Dedupe listing endpoint returns our pre-seeded plan
        if ($request->method() === 'GET') {
            return Http::response(['status' => true, 'data' => [[
                'name' => 'ShootingSports Micro (Monthly)',
                'interval' => 'monthly',
                'plan_code' => 'PLN_existing',
                'amount' => 11500,
            ]]], 200);
        }

        $body = $request->data();

        return Http::response(['status' => true, 'data' => [
            'plan_code' => 'PLN_'.substr(sha1($body['name']), 0, 10),
            'name' => $body['name'],
            'interval' => $body['interval'],
            'amount' => $body['amount'],
        ]], 200);
    });

    PlatformBillingPlan::query()->insert([
        [
            'tier' => SubscriptionTier::Micro->value,
            'interval' => BillingInterval::Monthly->value,
            'price_cents' => SubscriptionTier::Micro->priceCents(BillingInterval::Monthly),
            'paystack_plan_code' => 'PLN_existing',
            'created_at' => now(), 'updated_at' => now(),
        ],
    ]);

    app(PaystackPlatformPlanService::class)->provisionPlans();

    expect(PlatformBillingPlan::count())->toBe(6);
    expect(PlatformBillingPlan::query()
        ->where('tier', SubscriptionTier::Micro->value)
        ->where('interval', BillingInterval::Monthly->value)
        ->value('paystack_plan_code'))
        ->toBe('PLN_existing');
});

it('startSubscription returns the Paystack authorize URL and stores the reference', function () {
    Http::fake([
        'api.paystack.co/transaction/initialize' => Http::response([
            'status' => true,
            'data' => [
                'reference' => 'ref_abc',
                'authorization_url' => 'https://checkout.paystack.com/abc',
                'access_code' => 'ac_abc',
            ],
        ], 200),
    ]);

    PlatformBillingPlan::query()->insert([
        'tier' => SubscriptionTier::Micro->value,
        'interval' => BillingInterval::Monthly->value,
        'price_cents' => SubscriptionTier::Micro->priceCents(BillingInterval::Monthly),
        'paystack_plan_code' => 'PLN_test',
        'created_at' => now(), 'updated_at' => now(),
    ]);

    $subscription = PlatformSubscription::factory()->create([
        'tier' => SubscriptionTier::Micro,
        'interval' => BillingInterval::Monthly,
    ]);

    $url = app(PaystackPlatformPlanService::class)
        ->startSubscription($subscription, 'admin@example.test', 'https://app.test/billing/callback');

    expect($url)->toBe('https://checkout.paystack.com/abc');
    expect($subscription->fresh()->paystack_reference)->toBe('ref_abc');
});
