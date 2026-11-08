<?php

use App\Enums\ClubPlan;
use App\Enums\OrganisationUserRole;
use App\Enums\Plan;
use App\Livewire\ClubPlanCheckout;
use App\Models\Organisation;
use App\Models\OrganisationUser;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;

beforeEach(function () {
    $this->withoutVite();
    config([
        'club-plans.enabled' => true,
        'club-plans.pricing.annual.amount_cents' => 100000,
        'club-plans.pricing.annual.display' => 'TODO annual',
        'club-plans.pricing.monthly.amount_cents' => 10000,
        'club-plans.pricing.monthly.display' => 'TODO monthly',
        'services.paystack.secret_key' => 'sk_test_dummy',
        'services.paystack.webhook_secret' => null,
        'services.paystack.base_url' => 'https://api.paystack.co',
        'services.paystack.club_plan_codes.annual' => 'PLN_club_annual',
        'services.paystack.club_plan_codes.monthly' => 'PLN_club_monthly',
    ]);
});

it('starts a club checkout that is not a shooter Pro charge', function () {
    Http::fake([
        'https://api.paystack.co/transaction/initialize' => Http::response([
            'status' => true,
            'data' => [
                'authorization_url' => 'https://checkout.paystack.com/club',
                'access_code' => 'ac_club',
                'reference' => 'ref_club',
            ],
        ]),
    ]);

    $club = Organisation::factory()->create();
    $secretary = User::factory()->create(['email' => 'secretary@example.com', 'plan' => Plan::Free]);
    OrganisationUser::create([
        'organisation_id' => $club->id,
        'user_id' => $secretary->id,
        'role' => OrganisationUserRole::Secretary->value,
        'granted_at' => now(),
    ]);

    $this->actingAs($secretary);

    Livewire::test(ClubPlanCheckout::class, ['organisation' => $club])
        ->call('checkout')
        ->assertRedirect('https://checkout.paystack.com/club');

    Http::assertSent(function ($request): bool {
        $body = $request->data();

        return $request->url() === 'https://api.paystack.co/transaction/initialize'
            && ($body['plan'] ?? null) === 'PLN_club_annual'
            && ($body['metadata']['purpose'] ?? null) === 'club_plan'
            && ($body['metadata']['organisation_id'] ?? null) === Organisation::query()->first()->id;
    });

    expect($secretary->fresh()->isPro())->toBeFalse();
});

it('activates the club from the webhook and leaves the director on Free', function () {
    $director = User::factory()->create([
        'email' => 'director@example.com',
        'plan' => Plan::Free,
    ]);
    $club = Organisation::factory()->create();

    $payload = [
        'id' => 'evt_club_1',
        'event' => 'charge.success',
        'data' => [
            'reference' => 'ref_club_charge',
            'customer' => ['email' => 'director@example.com', 'customer_code' => 'CUS_club'],
            'authorization' => ['authorization_code' => 'AUTH_club'],
            'plan' => ['interval' => 'annually', 'plan_code' => 'PLN_club_annual'],
            'plan_object' => ['subscription_code' => 'SUB_club'],
            'paid_at' => '2026-11-08T10:00:00.000Z',
            'metadata' => [
                'purpose' => 'club_plan',
                'organisation_id' => $club->id,
                'cycle' => 'annual',
            ],
        ],
    ];

    $body = json_encode($payload);
    $this->call(
        method: 'POST',
        uri: '/paystack/webhook',
        server: [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_X_PAYSTACK_SIGNATURE' => hash_hmac('sha512', $body, 'sk_test_dummy'),
        ],
        content: $body,
    )->assertOk();

    $club->refresh();
    expect($club->isPaid())->toBeTrue()
        ->and($club->clubPlan())->toBe(ClubPlan::Club)
        ->and($club->paystack_subscription_code)->toBe('SUB_club')
        ->and($director->fresh()->isPro())->toBeFalse();
});

it('cancels the club subscription without cancelling the director Pro plan', function () {
    $director = User::factory()->create([
        'plan' => Plan::Pro,
        'plan_expires_at' => now()->addYear(),
        'paystack_subscription_code' => 'SUB_shooter',
    ]);
    $club = Organisation::factory()->clubPlan()->create([
        'paystack_subscription_code' => 'SUB_club_cancel',
        'paystack_customer_code' => 'CUS_club',
    ]);

    $payload = [
        'id' => 'evt_club_cancel',
        'event' => 'subscription.disable',
        'data' => [
            'subscription_code' => 'SUB_club_cancel',
            'customer' => ['email' => $director->email],
        ],
    ];

    $body = json_encode($payload);
    $this->call(
        method: 'POST',
        uri: '/paystack/webhook',
        server: [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_X_PAYSTACK_SIGNATURE' => hash_hmac('sha512', $body, 'sk_test_dummy'),
        ],
        content: $body,
    )->assertOk();

    expect($club->fresh()->plan_cancelled_at)->not->toBeNull()
        ->and($club->fresh()->isPaid())->toBeTrue()
        ->and($director->fresh()->plan_cancelled_at)->toBeNull()
        ->and($director->fresh()->isPro())->toBeTrue();
});
