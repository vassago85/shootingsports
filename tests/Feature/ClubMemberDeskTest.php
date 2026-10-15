<?php

use App\Enums\ClubMemberStatus;
use App\Enums\OrganisationUserRole;
use App\Filament\Desk\Resources\ClubMembers\ClubMemberResource;
use App\Filament\Desk\Resources\ClubMembers\Pages\ListClubMembers;
use App\Mail\ClubApplicationDecisionMail;
use App\Models\ClubMember;
use App\Models\Organisation;
use App\Models\OrganisationUser;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;

function attachRole(User $user, Organisation $organisation, OrganisationUserRole $role): void
{
    OrganisationUser::create([
        'organisation_id' => $organisation->id,
        'user_id' => $user->id,
        'role' => $role->value,
        'granted_at' => now(),
    ]);
}

beforeEach(function () {
    Filament::setCurrentPanel('desk');
});

it('scopes the list to clubs the user manages', function () {
    $mine = Organisation::factory()->create();
    $theirs = Organisation::factory()->create();

    $secretary = User::factory()->create();
    attachRole($secretary, $mine, OrganisationUserRole::Secretary);

    ClubMember::factory()->for($mine, 'organisation')->create(['first_name' => 'Visible']);
    ClubMember::factory()->for($theirs, 'organisation')->create(['first_name' => 'Hidden']);

    $this->actingAs($secretary);

    Livewire::test(ListClubMembers::class)
        ->assertCanSeeTableRecords(ClubMember::query()->where('organisation_id', $mine->id)->get())
        ->assertCanNotSeeTableRecords(ClubMember::query()->where('organisation_id', $theirs->id)->get());
});

it('blocks a match director without the member-admin role', function () {
    $club = Organisation::factory()->create();
    $md = User::factory()->matchDirector()->create();
    attachRole($md, $club, OrganisationUserRole::MatchDirector);

    $this->actingAs($md);

    expect(ClubMemberResource::canViewAny())->toBeFalse();
});

it('approves an application, writes history and emails the applicant', function () {
    Mail::fake();

    $club = Organisation::factory()->create();
    $admin = User::factory()->create();
    attachRole($admin, $club, OrganisationUserRole::Admin);

    $application = ClubMember::factory()
        ->for($club, 'organisation')
        ->applied()
        ->create(['email' => 'applicant@example.com']);

    $this->actingAs($admin);

    Livewire::test(ListClubMembers::class)
        ->callTableAction('approve', $application);

    $application->refresh();
    expect($application->status)->toBe(ClubMemberStatus::Active)
        ->and($application->joined_at)->not->toBeNull()
        ->and($application->statusChanges()->count())->toBe(1)
        ->and($application->statusChanges()->first()->to_status)->toBe(ClubMemberStatus::Active);

    Mail::assertQueued(ClubApplicationDecisionMail::class, fn (ClubApplicationDecisionMail $m): bool => $m->hasTo('applicant@example.com') && $m->approved === true);
});

it('rejects an application with a reason and emails the applicant', function () {
    Mail::fake();

    $club = Organisation::factory()->create();
    $admin = User::factory()->create();
    attachRole($admin, $club, OrganisationUserRole::Admin);

    $application = ClubMember::factory()
        ->for($club, 'organisation')
        ->applied()
        ->create(['email' => 'nope@example.com']);

    $this->actingAs($admin);

    Livewire::test(ListClubMembers::class)
        ->callTableAction('reject', $application, ['reason' => 'Insufficient attendance.']);

    $application->refresh();
    expect($application->status)->toBe(ClubMemberStatus::Rejected)
        ->and($application->statusChanges()->latest('id')->first()->reason)->toBe('Insufficient attendance.');

    Mail::assertQueued(ClubApplicationDecisionMail::class, fn (ClubApplicationDecisionMail $m): bool => $m->hasTo('nope@example.com') && $m->approved === false && $m->reason === 'Insufficient attendance.');
});

it('reports an expired active member as lapsed', function () {
    $member = ClubMember::factory()
        ->expired()
        ->create();

    expect($member->effectiveStatus())->toBe(ClubMemberStatus::Lapsed)
        ->and($member->status)->toBe(ClubMemberStatus::Active);
});
