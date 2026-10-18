<?php

use App\Enums\EventPublication;
use App\Enums\MembershipStatus;
use App\Enums\OrganisationUserRole;
use App\Enums\UserRole;
use App\Models\Event;
use App\Models\Organisation;
use App\Models\OrganisationUser;
use App\Models\User;

beforeEach(function () {
    $this->withoutVite();
});

// --- Role column + helpers ---------------------------------------------

it('backfills existing staff flag into the role column', function () {
    // Reset role so we can observe the command's work (the migration
    // already populates it for anyone with is_staff=true at migrate
    // time; this test exercises later-added rows via the command).
    $staff = User::factory()->create(['is_staff' => true]);
    $shooter = User::factory()->create(['is_staff' => false]);
    $staff->forceFill(['role' => UserRole::User])->save();

    $exit = $this->artisan('app:phase-b-backfill-memberships')->run();

    expect($exit)->toBe(0)
        ->and($staff->fresh()->role)->toBe(UserRole::Staff)
        ->and($staff->fresh()->isStaff())->toBeTrue()
        ->and($shooter->fresh()->role)->toBe(UserRole::User)
        ->and($shooter->fresh()->isStaff())->toBeFalse();
});

it('is idempotent — running the backfill twice does not double-insert', function () {
    $director = User::factory()->create([
        'is_match_director' => true,
        'md_approved_at' => now(),
    ]);
    $club = Organisation::factory()->create();
    Event::factory()->create([
        'host_organisation_id' => $club->id,
        'created_by' => $director->id,
    ]);

    $this->artisan('app:phase-b-backfill-memberships')->assertSuccessful();
    $this->artisan('app:phase-b-backfill-memberships')->assertSuccessful();

    expect(OrganisationUser::query()
        ->where('user_id', $director->id)
        ->where('organisation_id', $club->id)
        ->count()
    )->toBe(1);
});

// --- Match director backfill -------------------------------------------

it('inserts an approved organisation_user row for every host club the director has posted an event to', function () {
    $director = User::factory()->create([
        'is_match_director' => true,
        'md_approved_at' => now(),
    ]);
    [$clubA, $clubB] = Organisation::factory()->count(2)->create();
    Event::factory()->create([
        'host_organisation_id' => $clubA->id,
        'created_by' => $director->id,
    ]);
    Event::factory()->create([
        'host_organisation_id' => $clubB->id,
        'created_by' => $director->id,
    ]);

    $this->artisan('app:phase-b-backfill-memberships')->assertSuccessful();

    $rows = OrganisationUser::query()
        ->where('user_id', $director->id)
        ->get();

    expect($rows)->toHaveCount(2)
        ->and($rows->pluck('organisation_id')->sort()->values()->all())
        ->toBe(collect([$clubA->id, $clubB->id])->sort()->values()->all())
        ->and($rows->every(fn (OrganisationUser $row) => $row->isApproved()))->toBeTrue()
        ->and($rows->every(fn (OrganisationUser $row) => $row->role === OrganisationUserRole::MatchDirector))->toBeTrue();
});

it('does not backfill pending or rejected directors', function () {
    $pending = User::factory()->create([
        'is_match_director' => false,
        'md_requested_at' => now()->subDay(),
    ]);
    $club = Organisation::factory()->create();
    Event::factory()->create([
        'host_organisation_id' => $club->id,
        'created_by' => $pending->id,
    ]);

    $this->artisan('app:phase-b-backfill-memberships')->assertSuccessful();

    expect(OrganisationUser::query()->where('user_id', $pending->id)->count())->toBe(0);
});

it('preserves existing memberships rather than overwriting them', function () {
    $director = User::factory()->create([
        'is_match_director' => true,
        'md_approved_at' => now(),
    ]);
    $club = Organisation::factory()->create();
    Event::factory()->create([
        'host_organisation_id' => $club->id,
        'created_by' => $director->id,
    ]);

    // A staffer already set them up as a Secretary.
    OrganisationUser::query()->create([
        'user_id' => $director->id,
        'organisation_id' => $club->id,
        'role' => OrganisationUserRole::Secretary->value,
        'status' => MembershipStatus::Approved->value,
        'approved_at' => now()->subMonth(),
    ]);

    $this->artisan('app:phase-b-backfill-memberships')->assertSuccessful();

    $row = OrganisationUser::query()
        ->where('user_id', $director->id)
        ->where('organisation_id', $club->id)
        ->sole();

    expect($row->role)->toBe(OrganisationUserRole::Secretary);
});

// --- User + Organisation helpers ---------------------------------------

it('exposes managedOrganisations as approved pivots only', function () {
    $user = User::factory()->create();
    [$clubA, $clubB] = Organisation::factory()->count(2)->create();

    OrganisationUser::query()->create([
        'user_id' => $user->id,
        'organisation_id' => $clubA->id,
        'role' => OrganisationUserRole::MatchDirector->value,
        'status' => MembershipStatus::Approved->value,
        'approved_at' => now(),
    ]);
    OrganisationUser::query()->create([
        'user_id' => $user->id,
        'organisation_id' => $clubB->id,
        'role' => OrganisationUserRole::MatchDirector->value,
        'status' => MembershipStatus::Pending->value,
        'requested_at' => now(),
    ]);

    expect($user->managedOrganisations->pluck('id')->all())->toBe([$clubA->id])
        ->and($user->managesOrganisation($clubA))->toBeTrue()
        ->and($user->managesOrganisation($clubB))->toBeFalse()
        ->and($user->hasDeskAccess())->toBeTrue();
});

it('exposes organisation.managers as approved pivots only', function () {
    $club = Organisation::factory()->create();
    $approved = User::factory()->create();
    $pending = User::factory()->create();

    OrganisationUser::query()->create([
        'user_id' => $approved->id,
        'organisation_id' => $club->id,
        'role' => OrganisationUserRole::MatchDirector->value,
        'status' => MembershipStatus::Approved->value,
        'approved_at' => now(),
    ]);
    OrganisationUser::query()->create([
        'user_id' => $pending->id,
        'organisation_id' => $club->id,
        'role' => OrganisationUserRole::MatchDirector->value,
        'status' => MembershipStatus::Pending->value,
        'requested_at' => now(),
    ]);

    expect($club->managers->pluck('id')->all())->toBe([$approved->id]);
});

// --- Publication + trusted ---------------------------------------------

it('casts the new event publication column to the EventPublication enum', function () {
    $event = Event::factory()->create(['publication' => EventPublication::PendingReview->value]);

    expect($event->fresh()->publication)->toBe(EventPublication::PendingReview);
});

it('casts the new trusted column on organisations to a boolean', function () {
    $trusted = Organisation::factory()->create(['trusted' => true]);
    $untrusted = Organisation::factory()->create();

    expect($trusted->fresh()->trusted)->toBeTrue()
        ->and($untrusted->fresh()->trusted)->toBeFalse();
});
