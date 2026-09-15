<?php

use App\Enums\Plan;
use App\Livewire\Upgrade;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;

beforeEach(function () {
    $this->withoutVite();

    config([
        'services.paystack.secret_key' => 'sk_test_dummy',
        'services.paystack.base_url' => 'https://api.paystack.co',
        'services.paystack.plan_codes.annual' => 'PLN_annual',
        'services.paystack.plan_codes.monthly' => 'PLN_monthly',
    ]);
});

it('guests hitting /upgrade are redirected to the login page', function () {
    $this->get('/upgrade')->assertRedirect(route('login'));
});

it('a free signed-in user sees both pricing cards and can pick annual or monthly', function () {
    $user = User::factory()->create(['plan' => Plan::Free]);

    Livewire::actingAs($user)
        ->test(Upgrade::class)
        ->assertSet('selected', 'annual')
        ->assertSee('R299 / year')
        ->assertSee('R29 / month')
        ->call('pick', 'monthly')
        ->assertSet('selected', 'monthly')
        ->call('pick', 'annual')
        ->assertSet('selected', 'annual');
});

it('the checkout button initialises a Paystack transaction and redirects out of Livewire', function () {
    $user = User::factory()->create(['plan' => Plan::Free, 'email' => 'go@example.com']);

    Http::fake([
        'https://api.paystack.co/transaction/initialize' => Http::response([
            'status' => true,
            'data' => [
                'authorization_url' => 'https://checkout.paystack.com/pay_abc',
                'access_code' => 'access_abc',
                'reference' => 'ref_from_paystack',
            ],
        ]),
    ]);

    Livewire::actingAs($user)
        ->test(Upgrade::class)
        ->call('pick', 'monthly')
        ->call('checkout')
        ->assertRedirect('https://checkout.paystack.com/pay_abc');

    Http::assertSent(function ($request) use ($user) {
        $body = json_decode($request->body(), true);

        return $body['plan'] === 'PLN_monthly'
            && $body['amount'] === 2900
            && $body['email'] === 'go@example.com'
            && $body['metadata']['user_id'] === $user->id
            && $body['metadata']['cycle'] === 'monthly';
    });
});

it('renders the waitlist-only variant when Paystack is not configured yet', function () {
    config([
        'services.paystack.plan_codes.annual' => null,
        'services.paystack.plan_codes.monthly' => null,
    ]);

    $user = User::factory()->create(['plan' => Plan::Free]);

    Livewire::actingAs($user)
        ->test(Upgrade::class)
        ->assertSee('Pro is not open for subscriptions yet')
        ->assertDontSee('Continue to secure checkout');
});

it('an active subscriber sees a cancel button, not pricing', function () {
    $user = User::factory()->create([
        'plan' => Plan::Pro,
        'plan_billing_cycle' => 'annual',
        'plan_expires_at' => now()->addYear(),
        'paystack_subscription_code' => 'SUB_active',
        'paystack_customer_code' => 'CUS_active',
    ]);

    Livewire::actingAs($user)
        ->test(Upgrade::class)
        ->assertSee('Your subscription')
        ->assertSee('Cancel subscription')
        ->assertDontSee('Continue to secure checkout');
});

it('POST /upgrade/cancel disables the subscription on Paystack and marks the user as cancelling', function () {
    $user = User::factory()->create([
        'plan' => Plan::Pro,
        'plan_billing_cycle' => 'annual',
        'plan_expires_at' => now()->addMonths(6),
        'paystack_subscription_code' => 'SUB_x',
    ]);

    Http::fake([
        'https://api.paystack.co/subscription/SUB_x' => Http::response([
            'status' => true,
            'data' => [
                'subscription_code' => 'SUB_x',
                'email_token' => 'tok_y',
            ],
        ]),
        'https://api.paystack.co/subscription/disable' => Http::response([
            'status' => true,
            'message' => 'Subscription disabled',
        ]),
    ]);

    $this->actingAs($user)
        ->post('/upgrade/cancel')
        ->assertRedirect('/upgrade')
        ->assertSessionHas('status');

    Http::assertSent(function ($request) {
        if ($request->url() !== 'https://api.paystack.co/subscription/disable') {
            return false;
        }
        $body = json_decode($request->body(), true);

        return $body['code'] === 'SUB_x' && $body['token'] === 'tok_y';
    });

    $user->refresh();

    expect($user->plan_cancelled_at)->not->toBeNull()
        ->and($user->isCancelling())->toBeTrue()
        ->and($user->isPro())->toBeTrue(); // still Pro until plan_expires_at
});

it('POST /upgrade/cancel is a no-op when the user has no active subscription', function () {
    $user = User::factory()->create(['plan' => Plan::Free]);

    $this->actingAs($user)
        ->post('/upgrade/cancel')
        ->assertRedirect('/upgrade');

    expect($user->fresh()->plan_cancelled_at)->toBeNull();

    Http::assertNothingSent();
});
