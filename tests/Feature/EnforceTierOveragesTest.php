<?php

use App\Enums\MembershipStatus;
use App\Enums\OrganisationUserRole;
use App\Enums\PlatformSubscriptionStatus;
use App\Enums\SubscriptionTier;
use App\Models\ClubMember;
use App\Models\Organisation;
use App\Models\PlatformSubscription;
use App\Models\User;
use Filament\Facades\Filament;

beforeEach(function (): void {
    config()->set('platform.grace_window_days', 30);
    config()->set('platform.tier_member_caps', ['micro' => 50, 'standard' => 250, 'large' => 1000]);
});

it('stamps a 30-day grace window when active count crosses the Micro cap', function () {
    $org = Organisation::factory()->create(['active_member_count' => 0]);
    PlatformSubscription::factory()->for($org)->create(['tier' => SubscriptionTier::Micro]);
    ClubMember::factory()->count(51)->for($org, 'organisation')->create();

    $this->artisan('platform:enforce-tier-overages')->assertSuccessful();

    $org->refresh();
    expect($org->active_member_count)->toBe(51)
        ->and($org->tier_grace_ends_at->isSameDay(today()->addDays(30)))->toBeTrue();
});

it('auto-upgrades once the grace window expires', function () {
    $org = Organisation::factory()->create(['tier_grace_ends_at' => today()->subDay()]);
    $sub = PlatformSubscription::factory()->for($org)->create(['tier' => SubscriptionTier::Micro]);
    ClubMember::factory()->count(51)->for($org, 'organisation')->create();

    $this->artisan('platform:enforce-tier-overages')->assertSuccessful();

    expect($sub->fresh()->tier)->toBe(SubscriptionTier::Standard)
        ->and($org->fresh()->tier_grace_ends_at)->toBeNull();
});

it('clears grace_ends_at when member count drops back below cap', function () {
    $org = Organisation::factory()->create([
        'active_member_count' => 51,
        'tier_grace_ends_at' => today()->addDays(10),
    ]);
    PlatformSubscription::factory()->for($org)->create(['tier' => SubscriptionTier::Micro]);
    ClubMember::factory()->count(40)->for($org, 'organisation')->create();

    $this->artisan('platform:enforce-tier-overages')->assertSuccessful();

    $org->refresh();
    expect($org->tier_grace_ends_at)->toBeNull()
        ->and($org->active_member_count)->toBe(40);
});

it('does not touch an org whose count is within tier cap', function () {
    $org = Organisation::factory()->create();
    $sub = PlatformSubscription::factory()->for($org)->create(['tier' => SubscriptionTier::Micro]);
    ClubMember::factory()->count(40)->for($org, 'organisation')->create();

    $this->artisan('platform:enforce-tier-overages')->assertSuccessful();

    expect($org->fresh()->tier_grace_ends_at)->toBeNull()
        ->and($sub->fresh()->tier)->toBe(SubscriptionTier::Micro);
});

it('--dry-run reports changes without writing', function () {
    $org = Organisation::factory()->create(['active_member_count' => 0]);
    PlatformSubscription::factory()->for($org)->create(['tier' => SubscriptionTier::Micro]);
    ClubMember::factory()->count(51)->for($org, 'organisation')->create();

    $this->artisan('platform:enforce-tier-overages', ['--dry-run' => true])
        ->expectsOutputToContain('grace window')
        ->assertSuccessful();

    $org->refresh();
    expect($org->tier_grace_ends_at)->toBeNull()
        ->and($org->active_member_count)->toBe(0);
});

it('auto-upgrade skips tiers when member count jumped multiple tiers', function () {
    $org = Organisation::factory()->create(['tier_grace_ends_at' => today()->subDay()]);
    $sub = PlatformSubscription::factory()->for($org)->create(['tier' => SubscriptionTier::Micro]);
    ClubMember::factory()->count(300)->for($org, 'organisation')->create();

    $this->artisan('platform:enforce-tier-overages')->assertSuccessful();

    expect($sub->fresh()->tier)->toBe(SubscriptionTier::Large);
});

it('recomputes the count but never auto-upgrades trial or cancelled subscriptions', function (PlatformSubscriptionStatus $status) {
    $org = Organisation::factory()->create(['tier_grace_ends_at' => today()->subDay()]);
    $sub = PlatformSubscription::factory()->for($org)->create([
        'tier' => SubscriptionTier::Micro,
        'status' => $status,
    ]);
    ClubMember::factory()->count(51)->for($org, 'organisation')->create();

    $this->artisan('platform:enforce-tier-overages')->assertSuccessful();

    expect($org->fresh()->active_member_count)->toBe(51)
        ->and($sub->fresh()->tier)->toBe(SubscriptionTier::Micro);
})->with([
    'trial' => PlatformSubscriptionStatus::Trial,
    'cancelled' => PlatformSubscriptionStatus::Cancelled,
]);

it('DeskDashboard shows an overage banner when the managed club is in grace', function () {
    $this->withoutVite();
    config()->set('coming-soon.enabled', false);
    Filament::setCurrentPanel('desk');

    $org = Organisation::factory()->create([
        'name' => 'Overflowing Rifle Club',
        'active_member_count' => 60,
        'tier_grace_ends_at' => today()->addDays(12),
    ]);
    PlatformSubscription::factory()->for($org)->create(['tier' => SubscriptionTier::Micro]);
    $admin = User::factory()->create();
    $org->users()->attach($admin->id, [
        'role' => OrganisationUserRole::Admin->value,
        'status' => MembershipStatus::Approved->value,
        'granted_at' => now(),
        'approved_at' => now(),
    ]);

    $this->actingAs($admin)
        ->get('/desk')
        ->assertOk()
        ->assertSee('Overflowing Rifle Club')
        ->assertSee('12 days');
});
