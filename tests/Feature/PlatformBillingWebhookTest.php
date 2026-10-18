<?php

use App\Enums\BillingInterval;
use App\Enums\PlatformSubscriptionStatus;
use App\Enums\SubscriptionTier;
use App\Models\PlatformSubscription;
use App\Services\Platform\PlatformBillingWebhookHandler;

it('transitions trial → active on charge.success with a platform_subscription reference', function () {
    $sub = PlatformSubscription::factory()->trial()->create([
        'tier' => SubscriptionTier::Micro,
        'interval' => BillingInterval::Monthly,
        'paystack_reference' => 'ref_abc',
    ]);

    app(PlatformBillingWebhookHandler::class)->handle('charge.success', [
        'reference' => 'ref_abc',
        'amount' => 11500,
        'metadata' => [
            'purpose' => 'platform_subscription',
            'subscription_id' => $sub->id,
        ],
    ]);

    $sub->refresh();

    expect($sub->status)->toBe(PlatformSubscriptionStatus::Active)
        ->and($sub->current_period_start->toDateString())->toBe(today()->toDateString())
        ->and($sub->current_period_end->toDateString())->toBe(today()->addMonth()->toDateString())
        ->and($sub->payments()->count())->toBe(1)
        ->and($sub->payments()->first()->amount_cents)->toBe(11500);
});

it('extends the period by a year for annual subscriptions and records a payment only once per reference', function () {
    $sub = PlatformSubscription::factory()->trial()->create([
        'interval' => BillingInterval::Annual,
        'paystack_reference' => 'ref_annual',
    ]);

    $data = [
        'reference' => 'ref_annual',
        'amount' => 115000,
        'metadata' => ['purpose' => 'platform_subscription'],
    ];

    app(PlatformBillingWebhookHandler::class)->handle('charge.success', $data);
    app(PlatformBillingWebhookHandler::class)->handle('charge.success', $data);

    $sub->refresh();

    expect($sub->current_period_end->toDateString())->toBe(today()->addYear()->toDateString())
        ->and($sub->payments()->count())->toBe(1);
});

it('transitions active → past_due on invoice.payment_failed', function () {
    $sub = PlatformSubscription::factory()->create([
        'status' => PlatformSubscriptionStatus::Active,
        'paystack_subscription_code' => 'SUB_xyz',
    ]);

    app(PlatformBillingWebhookHandler::class)->handle('invoice.payment_failed', [
        'subscription' => ['subscription_code' => 'SUB_xyz'],
    ]);

    expect($sub->fresh()->status)->toBe(PlatformSubscriptionStatus::PastDue);
});

it('returns past_due → active and extends the period on a successful invoice.update', function () {
    $sub = PlatformSubscription::factory()->pastDue()->create([
        'paystack_subscription_code' => 'SUB_renew',
        'current_period_end' => today(),
    ]);

    app(PlatformBillingWebhookHandler::class)->handle('invoice.update', [
        'status' => 'success',
        'paid' => true,
        'amount' => 11500,
        'subscription' => ['subscription_code' => 'SUB_renew'],
        'transaction' => ['reference' => 'ref_renewal_1'],
    ]);

    $sub->refresh();

    expect($sub->status)->toBe(PlatformSubscriptionStatus::Active)
        ->and($sub->current_period_end->toDateString())->toBe(today()->addMonth()->toDateString())
        ->and($sub->dunning_retry_count)->toBe(0)
        ->and($sub->payments()->count())->toBe(1);
});

it('stores paystack_subscription_code on subscription.create', function () {
    $sub = PlatformSubscription::factory()->create(['paystack_reference' => 'ref_abc']);

    app(PlatformBillingWebhookHandler::class)->handle('subscription.create', [
        'subscription_code' => 'SUB_new',
        'email_token' => 'tok_123',
        'customer' => ['customer_code' => 'CUS_new'],
        'metadata' => ['subscription_id' => $sub->id],
    ]);

    $sub->refresh();

    expect($sub->paystack_subscription_code)->toBe('SUB_new')
        ->and($sub->paystack_customer_code)->toBe('CUS_new')
        ->and($sub->paystack_email_token)->toBe('tok_123');
});

it('marks the subscription cancelled on subscription.disable', function () {
    $sub = PlatformSubscription::factory()->create(['paystack_subscription_code' => 'SUB_gone']);

    app(PlatformBillingWebhookHandler::class)->handle('subscription.disable', [
        'subscription_code' => 'SUB_gone',
    ]);

    expect($sub->fresh()->status)->toBe(PlatformSubscriptionStatus::Cancelled)
        ->and($sub->fresh()->cancelled_at)->not->toBeNull();
});

it('ignores charge.success events for other purposes', function () {
    $sub = PlatformSubscription::factory()->trial()->create();

    app(PlatformBillingWebhookHandler::class)->handle('charge.success', [
        'metadata' => ['purpose' => 'event_entry'],
        'reference' => 'other',
    ]);

    expect($sub->fresh()->status)->toBe(PlatformSubscriptionStatus::Trial)
        ->and($sub->payments()->count())->toBe(0);
});

it('routes a signed /paystack/webhook platform_subscription charge.success to the handler', function () {
    config(['services.paystack.secret_key' => 'sk_test_dummy']);

    $sub = PlatformSubscription::factory()->trial()->create([
        'paystack_reference' => 'ref_http',
    ]);

    $body = json_encode([
        'id' => 'evt_platform_1',
        'event' => 'charge.success',
        'data' => [
            'reference' => 'ref_http',
            'amount' => 11500,
            'metadata' => [
                'purpose' => 'platform_subscription',
                'subscription_id' => $sub->id,
            ],
        ],
    ]);

    $this->call(
        method: 'POST',
        uri: '/paystack/webhook',
        parameters: [],
        server: [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_X_PAYSTACK_SIGNATURE' => hash_hmac('sha512', $body, config('services.paystack.secret_key')),
        ],
        content: $body,
    )->assertOk()->assertJson(['status' => 'ok']);

    expect($sub->fresh()->status)->toBe(PlatformSubscriptionStatus::Active)
        ->and($sub->payments()->count())->toBe(1);
});

it('routes a metadata-less subscription.create to the platform handler via the stored customer code', function () {
    config(['services.paystack.secret_key' => 'sk_test_dummy']);

    $sub = PlatformSubscription::factory()->create([
        'paystack_customer_code' => 'CUS_http',
        'paystack_subscription_code' => null,
    ]);

    $body = json_encode([
        'id' => 'evt_platform_2',
        'event' => 'subscription.create',
        'data' => [
            'subscription_code' => 'SUB_http',
            'customer' => ['customer_code' => 'CUS_http', 'email' => 'admin@example.com'],
        ],
    ]);

    $this->call(
        method: 'POST',
        uri: '/paystack/webhook',
        parameters: [],
        server: [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_X_PAYSTACK_SIGNATURE' => hash_hmac('sha512', $body, config('services.paystack.secret_key')),
        ],
        content: $body,
    )->assertOk()->assertJson(['status' => 'ok']);

    expect($sub->fresh()->paystack_subscription_code)->toBe('SUB_http');
});
