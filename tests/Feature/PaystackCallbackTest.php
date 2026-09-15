<?php

use App\Enums\Plan;
use App\Models\User;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    config([
        'services.paystack.secret_key' => 'sk_test_dummy',
        'services.paystack.base_url' => 'https://api.paystack.co',
        'services.paystack.plan_codes.annual' => 'PLN_annual',
        'services.paystack.plan_codes.monthly' => 'PLN_monthly',
    ]);
});

/**
 * Successful post-checkout redirect from Paystack. The reference in
 * the query verifies, so we upgrade the user and land on /my-calendar
 * with a welcome flash.
 */
it('verifies a successful transaction and upgrades the user to Pro', function () {
    $user = User::factory()->create(['email' => 'shooter@example.com', 'plan' => Plan::Free]);

    Http::fake([
        'https://api.paystack.co/transaction/verify/ref_ok' => Http::response([
            'status' => true,
            'message' => 'Verification successful',
            'data' => [
                'status' => 'success',
                'reference' => 'ref_ok',
                'metadata' => ['user_id' => $user->id, 'cycle' => 'annual'],
                'customer' => ['email' => 'shooter@example.com', 'customer_code' => 'CUS_abc'],
                'authorization' => ['authorization_code' => 'AUTH_abc'],
                'plan_object' => ['subscription_code' => 'SUB_abc'],
                'plan' => ['interval' => 'annually'],
                'paid_at' => '2026-09-15T10:00:00.000Z',
            ],
        ]),
    ]);

    $response = $this->actingAs($user)->get('/paystack/callback?reference=ref_ok');

    $response->assertRedirect('/my-calendar');
    $response->assertSessionHas('status');

    $user->refresh();

    expect($user->plan)->toBe(Plan::Pro)
        ->and($user->plan_billing_cycle)->toBe('annual')
        ->and($user->paystack_customer_code)->toBe('CUS_abc')
        ->and($user->paystack_subscription_code)->toBe('SUB_abc')
        ->and($user->paystack_authorization_code)->toBe('AUTH_abc')
        ->and($user->plan_expires_at?->isFuture())->toBeTrue();
});

it('does nothing and redirects to /upgrade when the reference is missing', function () {
    $user = User::factory()->create(['plan' => Plan::Free]);

    $this->actingAs($user)
        ->get('/paystack/callback')
        ->assertRedirect('/upgrade');

    expect($user->fresh()->plan)->toBe(Plan::Free);
});

it('does not upgrade when Paystack reports the transaction was not successful', function () {
    $user = User::factory()->create(['email' => 'a@b.com', 'plan' => Plan::Free]);

    Http::fake([
        'https://api.paystack.co/transaction/verify/ref_fail' => Http::response([
            'status' => true,
            'message' => 'Verified',
            'data' => [
                'status' => 'abandoned',
                'reference' => 'ref_fail',
                'metadata' => ['user_id' => $user->id, 'cycle' => 'monthly'],
                'customer' => ['email' => 'a@b.com'],
            ],
        ]),
    ]);

    $this->actingAs($user)
        ->get('/paystack/callback?reference=ref_fail')
        ->assertRedirect('/upgrade');

    expect($user->fresh()->plan)->toBe(Plan::Free);
});

it('logs the user in if the callback lands them without a session', function () {
    $user = User::factory()->create(['email' => 'lonewolf@example.com', 'plan' => Plan::Free]);

    Http::fake([
        'https://api.paystack.co/transaction/verify/ref_login' => Http::response([
            'status' => true,
            'data' => [
                'status' => 'success',
                'reference' => 'ref_login',
                'metadata' => ['user_id' => $user->id, 'cycle' => 'monthly'],
                'customer' => ['email' => 'lonewolf@example.com', 'customer_code' => 'CUS_l'],
                'authorization' => ['authorization_code' => 'AUTH_l'],
                'plan_object' => ['subscription_code' => 'SUB_l'],
                'plan' => ['interval' => 'monthly'],
                'paid_at' => now()->toIso8601String(),
            ],
        ]),
    ]);

    // No actingAs() — Paystack can deliver the customer back in a
    // fresh browser session after a phone payment.
    $this->get('/paystack/callback?reference=ref_login')
        ->assertRedirect('/my-calendar');

    expect(auth()->id())->toBe($user->id)
        ->and($user->fresh()->plan)->toBe(Plan::Pro);
});
