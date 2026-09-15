<?php

use Illuminate\Support\Facades\Http;

beforeEach(function () {
    config([
        'services.paystack.secret_key' => 'sk_test_dummy',
        'services.paystack.base_url' => 'https://api.paystack.co',
        'services.paystack.currency' => 'ZAR',
        'plans.pricing.annual.amount_cents' => 29900,
        'plans.pricing.monthly.amount_cents' => 2900,
    ]);
});

it('fails when PAYSTACK_SECRET_KEY is not set', function () {
    config(['services.paystack.secret_key' => null]);

    $this->artisan('paystack:setup-plans')
        ->assertExitCode(1);
});

it('creates both plans and prints their codes when neither exists', function () {
    Http::fake([
        // 1) List plans — empty on a fresh account.
        'https://api.paystack.co/plan?perPage=100' => Http::response([
            'status' => true,
            'data' => [],
        ]),
        // 2) Create annual (first POST).
        'https://api.paystack.co/plan' => Http::sequence()
            ->push([
                'status' => true,
                'data' => [
                    'plan_code' => 'PLN_new_annual',
                    'name' => 'Shooting Sports Pro (Annual)',
                    'interval' => 'annually',
                    'amount' => 29900,
                ],
            ])
            ->push([
                'status' => true,
                'data' => [
                    'plan_code' => 'PLN_new_monthly',
                    'name' => 'Shooting Sports Pro (Monthly)',
                    'interval' => 'monthly',
                    'amount' => 2900,
                ],
            ]),
    ]);

    $this->artisan('paystack:setup-plans')
        ->expectsOutputToContain('PAYSTACK_PLAN_ANNUAL=PLN_new_annual')
        ->expectsOutputToContain('PAYSTACK_PLAN_MONTHLY=PLN_new_monthly')
        ->assertExitCode(0);
});

it('reuses existing plans when they already match by name and interval (idempotent)', function () {
    Http::fake([
        'https://api.paystack.co/plan?perPage=100' => Http::response([
            'status' => true,
            'data' => [
                [
                    'plan_code' => 'PLN_existing_annual',
                    'name' => 'Shooting Sports Pro (Annual)',
                    'interval' => 'annually',
                    'amount' => 29900,
                ],
                [
                    'plan_code' => 'PLN_existing_monthly',
                    'name' => 'Shooting Sports Pro (Monthly)',
                    'interval' => 'monthly',
                    'amount' => 2900,
                ],
            ],
        ]),
    ]);

    $this->artisan('paystack:setup-plans')
        ->expectsOutputToContain('PAYSTACK_PLAN_ANNUAL=PLN_existing_annual')
        ->expectsOutputToContain('PAYSTACK_PLAN_MONTHLY=PLN_existing_monthly')
        ->assertExitCode(0);

    // No POST /plan should have been sent — we found both existing.
    // (Http::assertSentCount also counts the GETs; the list endpoint
    // is called TWICE — once per createPlan call — so total = 2.)
    Http::assertSentCount(2);
});
