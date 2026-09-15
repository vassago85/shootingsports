<?php

use App\Services\Paystack\PaystackClient;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    config([
        'services.paystack.secret_key' => 'sk_test_dummy',
        'services.paystack.base_url' => 'https://api.paystack.co',
        'services.paystack.currency' => 'ZAR',
    ]);
});

it('initialises a transaction with the plan code and returns the authorization url', function () {
    Http::fake([
        'https://api.paystack.co/transaction/initialize' => Http::response([
            'status' => true,
            'message' => 'Authorization URL created',
            'data' => [
                'authorization_url' => 'https://checkout.paystack.com/abc123',
                'access_code' => 'access_abc',
                'reference' => 'ref_from_paystack',
            ],
        ]),
    ]);

    $client = new PaystackClient;

    $result = $client->initializeTransaction(
        email: 'shooter@example.com',
        amountCents: 29900,
        planCode: 'PLN_annual',
        callbackUrl: 'https://example.test/paystack/callback',
        reference: 'ref_local_1',
        metadata: ['user_id' => 42, 'cycle' => 'annual'],
    );

    expect($result)->toMatchArray([
        'authorization_url' => 'https://checkout.paystack.com/abc123',
        'access_code' => 'access_abc',
        'reference' => 'ref_from_paystack',
    ]);

    Http::assertSent(function ($request) {
        $body = json_decode($request->body(), true);

        return $request->url() === 'https://api.paystack.co/transaction/initialize'
            && $request->hasHeader('Authorization', 'Bearer sk_test_dummy')
            && $body['email'] === 'shooter@example.com'
            && $body['amount'] === 29900
            && $body['plan'] === 'PLN_annual'
            && $body['currency'] === 'ZAR'
            && $body['callback_url'] === 'https://example.test/paystack/callback'
            && $body['reference'] === 'ref_local_1'
            && $body['metadata']['user_id'] === 42;
    });
});

it('throws when Paystack returns status false', function () {
    Http::fake([
        'https://api.paystack.co/transaction/initialize' => Http::response([
            'status' => false,
            'message' => 'Invalid key',
        ]),
    ]);

    $client = new PaystackClient;

    expect(fn () => $client->initializeTransaction(
        email: 'x@y.com',
        amountCents: 1000,
        planCode: 'PLN_x',
        callbackUrl: 'https://x.test/cb',
    ))->toThrow(RuntimeException::class, 'Invalid key');
});

it('verifies a transaction and returns the data envelope', function () {
    Http::fake([
        'https://api.paystack.co/transaction/verify/ref_verify' => Http::response([
            'status' => true,
            'message' => 'Verification successful',
            'data' => [
                'status' => 'success',
                'reference' => 'ref_verify',
                'customer' => ['email' => 'shooter@example.com', 'customer_code' => 'CUS_x'],
                'authorization' => ['authorization_code' => 'AUTH_x'],
                'plan_object' => ['subscription_code' => 'SUB_x'],
                'paid_at' => '2026-09-15T10:00:00.000Z',
            ],
        ]),
    ]);

    $data = (new PaystackClient)->verifyTransaction('ref_verify');

    expect($data['status'])->toBe('success')
        ->and($data['customer']['customer_code'])->toBe('CUS_x');
});

it('does not create a duplicate plan when one with the same name and interval already exists', function () {
    Http::fake([
        'https://api.paystack.co/plan?perPage=100' => Http::response([
            'status' => true,
            'data' => [
                [
                    'name' => 'Shooting Sports Pro (Annual)',
                    'interval' => 'annually',
                    'plan_code' => 'PLN_existing_annual',
                    'amount' => 29900,
                ],
            ],
        ]),
    ]);

    $result = (new PaystackClient)->createPlan('Shooting Sports Pro (Annual)', 'annually', 29900);

    expect($result)->toMatchArray([
        'plan_code' => 'PLN_existing_annual',
        'created' => false,
    ]);

    // Only the LIST call should have gone out, no POST /plan.
    Http::assertSentCount(1);
});

it('creates a new plan when the name and interval do not match', function () {
    Http::fake([
        'https://api.paystack.co/plan?perPage=100' => Http::response([
            'status' => true,
            'data' => [],
        ]),
        'https://api.paystack.co/plan' => Http::response([
            'status' => true,
            'data' => [
                'plan_code' => 'PLN_new_monthly',
                'name' => 'Shooting Sports Pro (Monthly)',
                'interval' => 'monthly',
                'amount' => 2900,
            ],
        ]),
    ]);

    $result = (new PaystackClient)->createPlan('Shooting Sports Pro (Monthly)', 'monthly', 2900);

    expect($result)->toMatchArray([
        'plan_code' => 'PLN_new_monthly',
        'created' => true,
    ]);
});

it('verifies webhook signatures against the raw body', function () {
    $secret = 'sk_test_dummy';
    $body = '{"event":"charge.success","data":{"reference":"abc"}}';
    $good = hash_hmac('sha512', $body, $secret);
    $bad = str_repeat('0', 128);

    expect(PaystackClient::verifyWebhookSignature($body, $good, $secret))->toBeTrue()
        ->and(PaystackClient::verifyWebhookSignature($body, $bad, $secret))->toBeFalse()
        // Any re-serialisation of the JSON must invalidate the signature.
        ->and(PaystackClient::verifyWebhookSignature($body.' ', $good, $secret))->toBeFalse();
});

it('cancels a subscription with the code and email token', function () {
    Http::fake([
        'https://api.paystack.co/subscription/disable' => Http::response([
            'status' => true,
            'message' => 'Subscription disabled',
        ]),
    ]);

    (new PaystackClient)->disableSubscription('SUB_x', 'tok_y');

    Http::assertSent(function ($request) {
        $body = json_decode($request->body(), true);

        return $request->url() === 'https://api.paystack.co/subscription/disable'
            && $body['code'] === 'SUB_x'
            && $body['token'] === 'tok_y';
    });
});
