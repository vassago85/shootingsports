<?php

use App\Enums\ClubMemberStatus;
use App\Enums\OrganisationUserRole;
use App\Filament\Desk\Resources\ClubMembers\Pages\ListClubMembers;
use App\Mail\ClubApplicationDecisionMail;
use App\Models\ClubMember;
use App\Models\Organisation;
use App\Models\OrganisationUser;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;

function clubWithAdmin(): array
{
    $club = Organisation::factory()->create();
    $admin = User::factory()->create();
    OrganisationUser::create([
        'organisation_id' => $club->id,
        'user_id' => $admin->id,
        'role' => OrganisationUserRole::Admin->value,
        'granted_at' => now(),
    ]);

    return [$club, $admin];
}

beforeEach(function () {
    Filament::setCurrentPanel('desk');
});

it('search finds a member by phone or member number', function () {
    [$club, $admin] = clubWithAdmin();

    $target = ClubMember::factory()->for($club, 'organisation')->create([
        'member_number' => 'M-7788',
        'phone' => '082 555 7788',
        'first_name' => 'Jo',
        'last_name' => 'Smith',
    ]);
    $decoy = ClubMember::factory()->for($club, 'organisation')->create([
        'member_number' => 'M-0001',
        'phone' => '082 111 1111',
    ]);

    $this->actingAs($admin);

    Livewire::test(ListClubMembers::class)
        ->set('tableSearch', '7788')
        ->assertCanSeeTableRecords([$target])
        ->assertCanNotSeeTableRecords([$decoy])
        ->set('tableSearch', '082 111')
        ->assertCanSeeTableRecords([$decoy])
        ->assertCanNotSeeTableRecords([$target]);
});

it('the Lapsed status filter returns expired-active members', function () {
    [$club, $admin] = clubWithAdmin();

    $lapsed = ClubMember::factory()->for($club, 'organisation')->expired()->create();
    $current = ClubMember::factory()->for($club, 'organisation')->active()->create();

    $this->actingAs($admin);

    Livewire::test(ListClubMembers::class)
        ->filterTable('status', ClubMemberStatus::Lapsed->value)
        ->assertCanSeeTableRecords([$lapsed])
        ->assertCanNotSeeTableRecords([$current]);
});

it('the Active status filter excludes expired-active members', function () {
    [$club, $admin] = clubWithAdmin();

    $lapsed = ClubMember::factory()->for($club, 'organisation')->expired()->create();
    $current = ClubMember::factory()->for($club, 'organisation')->active()->create();

    $this->actingAs($admin);

    Livewire::test(ListClubMembers::class)
        ->filterTable('status', ClubMemberStatus::Active->value)
        ->assertCanSeeTableRecords([$current])
        ->assertCanNotSeeTableRecords([$lapsed]);
});

it('the Current tab shows only members whose membership is current', function () {
    [$club, $admin] = clubWithAdmin();

    $current = ClubMember::factory()->for($club, 'organisation')->active()->create();
    $lapsed = ClubMember::factory()->for($club, 'organisation')->expired()->create();
    $applied = ClubMember::factory()->for($club, 'organisation')->applied()->create();

    $this->actingAs($admin);

    Livewire::test(ListClubMembers::class, ['activeTab' => 'current'])
        ->assertCanSeeTableRecords([$current])
        ->assertCanNotSeeTableRecords([$lapsed, $applied]);
});

it('the Lapsed tab shows only expired-active members', function () {
    [$club, $admin] = clubWithAdmin();

    $lapsed = ClubMember::factory()->for($club, 'organisation')->expired()->create();
    $current = ClubMember::factory()->for($club, 'organisation')->active()->create();

    $this->actingAs($admin);

    Livewire::test(ListClubMembers::class, ['activeTab' => 'lapsed'])
        ->assertCanSeeTableRecords([$lapsed])
        ->assertCanNotSeeTableRecords([$current]);
});

it('bulk approve activates every selected application and emails them', function () {
    Mail::fake();

    [$club, $admin] = clubWithAdmin();

    $a = ClubMember::factory()->for($club, 'organisation')->applied()->create(['email' => 'a@example.com']);
    $b = ClubMember::factory()->for($club, 'organisation')->applied()->create(['email' => 'b@example.com']);
    $alreadyActive = ClubMember::factory()->for($club, 'organisation')->active()->create();

    $this->actingAs($admin);

    Livewire::test(ListClubMembers::class)
        ->callTableBulkAction('bulk_approve', [$a, $b, $alreadyActive]);

    expect($a->fresh()->status)->toBe(ClubMemberStatus::Active)
        ->and($b->fresh()->status)->toBe(ClubMemberStatus::Active)
        ->and($alreadyActive->fresh()->status)->toBe(ClubMemberStatus::Active)
        ->and($a->fresh()->statusChanges()->count())->toBe(1)
        ->and($alreadyActive->fresh()->statusChanges()->count())->toBe(0);

    Mail::assertQueued(ClubApplicationDecisionMail::class, 2);
});

it('bulk reject marks every selected application rejected with a shared reason', function () {
    Mail::fake();

    [$club, $admin] = clubWithAdmin();

    $a = ClubMember::factory()->for($club, 'organisation')->applied()->create(['email' => 'a@example.com']);
    $b = ClubMember::factory()->for($club, 'organisation')->applied()->create(['email' => 'b@example.com']);
    $active = ClubMember::factory()->for($club, 'organisation')->active()->create();

    $this->actingAs($admin);

    Livewire::test(ListClubMembers::class)
        ->callTableBulkAction('bulk_reject', [$a, $b, $active], ['reason' => 'Register is full for 2026.']);

    expect($a->fresh()->status)->toBe(ClubMemberStatus::Rejected)
        ->and($b->fresh()->status)->toBe(ClubMemberStatus::Rejected)
        ->and($active->fresh()->status)->toBe(ClubMemberStatus::Active)
        ->and($a->fresh()->statusChanges()->latest('id')->first()->reason)->toBe('Register is full for 2026.');

    Mail::assertQueued(ClubApplicationDecisionMail::class, 2);
});
