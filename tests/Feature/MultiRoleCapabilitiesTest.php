<?php

use App\Enums\ClaimStatus;
use App\Enums\MembershipRole;
use App\Enums\MembershipStatus;
use App\Enums\OrganisationUserRole;
use App\Models\Claim;
use App\Models\Organisation;
use App\Models\OrganisationUser;
use App\Models\Provider;
use App\Models\ProviderMembership;
use App\Models\User;
use App\Models\Venue;
use App\Models\VenueMembership;
use Filament\Facades\Filament;

/*
 * Multi-role identity: one shooter account can stack any combination
 * of club, shop, range, and media capabilities. These tests prove the
 * desk's access gate and the per-range policy respect that model.
 */

beforeEach(function (): void {
    $this->withoutVite();
    config()->set('coming-soon.enabled', false);
});

it('does not give a plain shooter desk access', function () {
    $shooter = User::factory()->create();

    expect($shooter->hasDeskAccess())->toBeFalse();
});

it('gives desk access to a range-only owner through the venue_user pivot', function () {
    $owner = User::factory()->create();
    $range = Venue::factory()->create();

    VenueMembership::query()->create([
        'venue_id' => $range->id,
        'user_id' => $owner->id,
        'role' => MembershipRole::Owner->value,
        'status' => MembershipStatus::Approved->value,
        'approved_at' => now(),
    ]);

    expect($owner->fresh()->hasDeskAccess())->toBeTrue()
        ->and($owner->fresh()->isRangeManager())->toBeTrue()
        ->and($owner->fresh()->managedVenues()->count())->toBe(1);
});

it('gives desk access to a media-partner-only account', function () {
    $partner = User::factory()->mediaPartner()->create();

    expect($partner->hasDeskAccess())->toBeTrue();
});

it('still gives desk access when the user holds all four capabilities', function () {
    $user = User::factory()->mediaPartner()->create();
    $club = Organisation::factory()->create();
    $shop = Provider::factory()->create();
    $range = Venue::factory()->create();

    OrganisationUser::query()->create([
        'user_id' => $user->id,
        'organisation_id' => $club->id,
        'role' => OrganisationUserRole::Admin->value,
        'status' => MembershipStatus::Approved->value,
        'approved_at' => now(),
        'granted_at' => now(),
    ]);
    ProviderMembership::query()->create([
        'user_id' => $user->id,
        'provider_id' => $shop->id,
        'role' => MembershipRole::Owner->value,
        'status' => MembershipStatus::Approved->value,
        'approved_at' => now(),
    ]);
    VenueMembership::query()->create([
        'user_id' => $user->id,
        'venue_id' => $range->id,
        'role' => MembershipRole::Owner->value,
        'status' => MembershipStatus::Approved->value,
        'approved_at' => now(),
    ]);

    $user = $user->fresh();

    expect($user->hasDeskAccess())->toBeTrue()
        ->and($user->managedOrganisations()->count())->toBe(1)
        ->and($user->managedProviders()->count())->toBe(1)
        ->and($user->managedVenues()->count())->toBe(1)
        ->and($user->is_media_partner)->toBeTrue();
});

it('lets a range owner edit their own range but not someone else\'s', function () {
    $owner = User::factory()->create();
    $myRange = Venue::factory()->create();
    $otherRange = Venue::factory()->create();

    VenueMembership::query()->create([
        'venue_id' => $myRange->id,
        'user_id' => $owner->id,
        'role' => MembershipRole::Owner->value,
        'status' => MembershipStatus::Approved->value,
        'approved_at' => now(),
    ]);

    expect($owner->can('update', $myRange))->toBeTrue()
        ->and($owner->can('update', $otherRange))->toBeFalse();
});

it('promotes a shooter to range owner via the venue_user pivot when the claim is approved', function () {
    $staff = User::factory()->staff()->create();
    $user = User::factory()->create();
    $range = Venue::factory()->create(['claimed_by' => null]);

    $claim = Claim::query()->create([
        'claimable_type' => 'venue',
        'claimable_id' => $range->id,
        'user_id' => $user->id,
        'evidence' => 'I manage this range and the day-fee number on the page is ours.',
        'status' => ClaimStatus::Pending,
    ]);

    $claim->approve($staff);

    $membership = VenueMembership::query()
        ->where('venue_id', $range->id)
        ->where('user_id', $user->id)
        ->first();

    expect($range->fresh()->claimed_by)->toBe($user->id)
        ->and($membership)->not->toBeNull()
        ->and($membership->status)->toBe(MembershipStatus::Approved)
        ->and($membership->role)->toBe(MembershipRole::Owner)
        ->and($user->fresh()->hasDeskAccess())->toBeTrue();
});

it('shows a range claim card on the no-access desk landing', function () {
    $shooter = User::factory()->create();
    Filament::setCurrentPanel('desk');

    $this->actingAs($shooter)
        ->get('/desk')
        ->assertOk()
        ->assertSee('Claim a club')
        ->assertSee('List your business')
        ->assertSee('Claim a range');
});

/*
 * /me shooter home
 */

it('redirects a guest from /me to the login page', function () {
    $this->get('/me')->assertRedirect(route('login'));
});

it('renders the plain shooter /me with no Manage block', function () {
    $shooter = User::factory()->create(['name' => 'Jane Shooter']);

    $this->actingAs($shooter)
        ->get('/me')
        ->assertOk()
        ->assertSee('Welcome back, Jane Shooter')
        ->assertSee('My calendar')
        ->assertSee('My log')
        ->assertSee('My memberships')
        // Match the Manage heading element exactly so the meta
        // description copy ("...Manage area...") doesn't make this
        // false-fail.
        ->assertDontSee('id="me-manage-heading"', false)
        ->assertDontSee('data-test="capability-card"', false);
});

it('shows four capability cards on /me when the user holds every hat', function () {
    $user = User::factory()->mediaPartner()->create();
    $club = Organisation::factory()->create();
    $shop = Provider::factory()->create();
    $range = Venue::factory()->create();

    OrganisationUser::query()->create([
        'user_id' => $user->id,
        'organisation_id' => $club->id,
        'role' => OrganisationUserRole::Admin->value,
        'status' => MembershipStatus::Approved->value,
        'approved_at' => now(),
        'granted_at' => now(),
    ]);
    ProviderMembership::query()->create([
        'user_id' => $user->id,
        'provider_id' => $shop->id,
        'role' => MembershipRole::Owner->value,
        'status' => MembershipStatus::Approved->value,
        'approved_at' => now(),
    ]);
    VenueMembership::query()->create([
        'user_id' => $user->id,
        'venue_id' => $range->id,
        'role' => MembershipRole::Owner->value,
        'status' => MembershipStatus::Approved->value,
        'approved_at' => now(),
    ]);

    $this->actingAs($user)
        ->get('/me')
        ->assertOk()
        ->assertSee('Manage')
        ->assertSee('Clubs')
        ->assertSee('Shops')
        ->assertSee('Ranges')
        ->assertSee('Media')
        ->assertSee('Open clubs on the desk')
        ->assertSee('Open ranges on the desk')
        ->assertSee('Open the media desk');
});

it('hides capability cards on /me for a plain shooter', function () {
    $shooter = User::factory()->create();

    $this->actingAs($shooter)
        ->get('/me')
        ->assertOk()
        ->assertDontSee('data-test="capability-card"', false);
});

it('renders capability cards on the desk home when a user holds two or more hats', function () {
    $user = User::factory()->create();
    $club = Organisation::factory()->create();
    $range = Venue::factory()->create();

    OrganisationUser::query()->create([
        'user_id' => $user->id,
        'organisation_id' => $club->id,
        'role' => OrganisationUserRole::Admin->value,
        'status' => MembershipStatus::Approved->value,
        'approved_at' => now(),
        'granted_at' => now(),
    ]);
    VenueMembership::query()->create([
        'user_id' => $user->id,
        'venue_id' => $range->id,
        'role' => MembershipRole::Owner->value,
        'status' => MembershipStatus::Approved->value,
        'approved_at' => now(),
    ]);

    Filament::setCurrentPanel('desk');

    $this->actingAs($user)
        ->get('/desk')
        ->assertOk()
        ->assertSee('What you manage')
        ->assertSee('data-test="capability-card"', false)
        ->assertSee('Clubs')
        ->assertSee('Ranges');
});
