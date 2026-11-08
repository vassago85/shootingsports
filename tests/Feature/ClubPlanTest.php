<?php

use App\Enums\ClubPlan;
use App\Models\Organisation;
use App\Models\User;

it('starts a club on the free plan', function () {
    $club = Organisation::factory()->create();

    expect($club->clubPlan())->toBe(ClubPlan::Free)
        ->and($club->isPaid())->toBeFalse()
        ->and($club->allowsClubFeature('members'))->toBeFalse()
        ->and($club->allowsClubFeature('on_site_entries'))->toBeFalse()
        ->and($club->allowsClubFeature('embed'))->toBeFalse();
});

it('treats an open club subscription as paid', function () {
    $openEnded = Organisation::factory()->create([
        'plan' => ClubPlan::Club,
        'plan_expires_at' => null,
    ]);
    $renewing = Organisation::factory()->clubPlan()->create();

    expect($openEnded->isPaid())->toBeTrue()
        ->and($renewing->isPaid())->toBeTrue()
        ->and($renewing->allowsClubFeature('members'))->toBeTrue()
        ->and($renewing->allowsClubFeature('invoices'))->toBeTrue()
        ->and($renewing->allowsClubFeature('guest_days'))->toBeTrue()
        ->and($renewing->allowsClubFeature('reports'))->toBeTrue();
});

it('treats an expired club subscription as free', function () {
    $club = Organisation::factory()->create([
        'plan' => ClubPlan::Club,
        'plan_expires_at' => now()->subDay(),
    ]);

    expect($club->isPaid())->toBeFalse()
        ->and($club->effectiveClubPlan())->toBe(ClubPlan::Free)
        ->and($club->allowsClubFeature('members'))->toBeFalse();

    expect(Organisation::query()->paid()->whereKey($club->id)->exists())->toBeFalse();
});

it('keeps the club plan off the director account', function () {
    $director = User::factory()->create();
    $paid = Organisation::factory()->clubPlan()->create(['claimed_by' => $director->id]);
    $unpaid = Organisation::factory()->create(['claimed_by' => $director->id]);

    expect($paid->isPaid())->toBeTrue()
        ->and($unpaid->isPaid())->toBeFalse()
        ->and($director->isPro())->toBeFalse()
        ->and(Organisation::query()->paid()->pluck('id')->all())->toBe([$paid->id]);
});

it('leaves club prices unset until they are filled in', function () {
    expect(config('club-plans.pricing.annual.amount_cents'))->toBeNull()
        ->and(config('club-plans.pricing.monthly.amount_cents'))->toBeNull()
        ->and(config('club-plans.pricing.annual.display'))->toBeNull()
        ->and(config('services.paystack.club_plan_codes.annual'))->toBeNull()
        ->and(config('services.paystack.club_plan_codes.monthly'))->toBeNull();
});

it('rejects an unknown club feature key', function () {
    $club = Organisation::factory()->clubPlan()->create();

    $club->allowsClubFeature('not-a-feature');
})->throws(InvalidArgumentException::class);
