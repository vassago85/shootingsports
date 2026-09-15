<?php

use App\Enums\Plan;
use App\Models\User;
use Illuminate\Support\Facades\Gate;

it('defaults a new user to the free plan', function () {
    $user = User::factory()->create();

    expect($user->plan())->toBe(Plan::Free)
        ->and($user->isPro())->toBeFalse()
        ->and($user->effectivePlan())->toBe(Plan::Free);
});

it('treats a pro user with a future expiry as pro', function () {
    $user = User::factory()->create([
        'plan' => Plan::Pro,
        'plan_expires_at' => now()->addMonth(),
    ]);

    expect($user->isPro())->toBeTrue()
        ->and($user->effectivePlan())->toBe(Plan::Pro);
});

it('treats a pro user with no expiry as pro', function () {
    $user = User::factory()->create([
        'plan' => Plan::Pro,
        'plan_expires_at' => null,
    ]);

    expect($user->isPro())->toBeTrue();
});

it('degrades an expired pro user to free without a nightly job', function () {
    $user = User::factory()->create([
        'plan' => Plan::Pro,
        'plan_expires_at' => now()->subDay(),
    ]);

    expect($user->isPro())->toBeFalse()
        ->and($user->effectivePlan())->toBe(Plan::Free)
        ->and($user->limit('follows'))->toBe(3);
});

it('reads follow / saved search / history caps from config for free users', function () {
    $user = User::factory()->create();

    expect($user->limit('follows'))->toBe(3)
        ->and($user->limit('saved_searches'))->toBe(1)
        ->and($user->limit('history_months'))->toBe(12);
});

it('returns null (unlimited) for pro caps', function () {
    $user = User::factory()->create(['plan' => Plan::Pro]);

    expect($user->limit('follows'))->toBeNull()
        ->and($user->limit('saved_searches'))->toBeNull()
        ->and($user->limit('history_months'))->toBeNull();
});

it('rejects unknown limit keys instead of silently allowing writes', function () {
    $user = User::factory()->create();

    $user->limit('bogus');
})->throws(InvalidArgumentException::class);

it('withinLimit gates volume against the cap', function () {
    $user = User::factory()->create(); // free: 3 follows

    expect($user->withinLimit('follows', 0))->toBeTrue()
        ->and($user->withinLimit('follows', 2))->toBeTrue()
        ->and($user->withinLimit('follows', 3))->toBeFalse()
        ->and($user->withinLimit('follows', 10))->toBeFalse();
});

it('withinLimit always allows unlimited plans', function () {
    $user = User::factory()->create(['plan' => Plan::Pro]);

    expect($user->withinLimit('follows', 9_999))->toBeTrue();
});

it('remaining counts down from the cap and never returns negative', function () {
    $user = User::factory()->create();

    expect($user->remaining('follows', 0))->toBe(3)
        ->and($user->remaining('follows', 2))->toBe(1)
        ->and($user->remaining('follows', 3))->toBe(0)
        ->and($user->remaining('follows', 99))->toBe(0);
});

it('remaining is null for unlimited', function () {
    $user = User::factory()->create(['plan' => Plan::Pro]);

    expect($user->remaining('follows', 100))->toBeNull();
});

it('allows() reads boolean feature flags', function () {
    $free = User::factory()->create();
    $pro = User::factory()->create(['plan' => Plan::Pro]);

    expect($free->allows('export'))->toBeFalse()
        ->and($pro->allows('export'))->toBeTrue();
});

it('pro gate resolves through the trait', function () {
    $free = User::factory()->create();
    $pro = User::factory()->create(['plan' => Plan::Pro]);

    expect(Gate::forUser($free)->allows('pro'))->toBeFalse()
        ->and(Gate::forUser($pro)->allows('pro'))->toBeTrue();
});

it('unlimited-follows gate is true only for pro', function () {
    $free = User::factory()->create();
    $pro = User::factory()->create(['plan' => Plan::Pro]);

    expect(Gate::forUser($free)->allows('unlimited-follows'))->toBeFalse()
        ->and(Gate::forUser($pro)->allows('unlimited-follows'))->toBeTrue();
});

it('full-history gate is true only for pro', function () {
    $free = User::factory()->create();
    $pro = User::factory()->create(['plan' => Plan::Pro]);

    expect(Gate::forUser($free)->allows('full-history'))->toBeFalse()
        ->and(Gate::forUser($pro)->allows('full-history'))->toBeTrue();
});

it('export-season gate matches the config feature flag', function () {
    $free = User::factory()->create();
    $pro = User::factory()->create(['plan' => Plan::Pro]);

    expect(Gate::forUser($free)->allows('export-season'))->toBeFalse()
        ->and(Gate::forUser($pro)->allows('export-season'))->toBeTrue();
});

it('gates return false for guests', function () {
    expect(Gate::forUser(null)->allows('pro'))->toBeFalse()
        ->and(Gate::forUser(null)->allows('export-season'))->toBeFalse()
        ->and(Gate::forUser(null)->allows('unlimited-follows'))->toBeFalse()
        ->and(Gate::forUser(null)->allows('full-history'))->toBeFalse();
});
