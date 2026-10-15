<?php

use App\Enums\ClubMemberCategory;
use App\Enums\ClubMemberStatus;
use App\Enums\OrganisationUserRole;
use App\Livewire\ApplyForMembership;
use App\Mail\ClubApplicationReceivedMail;
use App\Models\ClubMember;
use App\Models\Organisation;
use App\Models\OrganisationUser;
use App\Models\User;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;

beforeEach(function () {
    Mail::fake();
});

function makeOpenClub(): Organisation
{
    return Organisation::factory()->create([
        'membership_enabled' => true,
        'accepts_applications' => true,
    ]);
}

function attachMemberAdmin(Organisation $organisation, OrganisationUserRole $role = OrganisationUserRole::Admin): User
{
    $admin = User::factory()->create();
    OrganisationUser::create([
        'organisation_id' => $organisation->id,
        'user_id' => $admin->id,
        'role' => $role->value,
        'granted_at' => now(),
    ]);

    return $admin;
}

it('creates an Applied record and emails club admins', function () {
    $club = makeOpenClub();
    $secretary = attachMemberAdmin($club, OrganisationUserRole::Secretary);
    $editor = attachMemberAdmin($club, OrganisationUserRole::Editor); // should NOT receive mail

    $shooter = User::factory()->create(['name' => 'Jana van der Merwe']);
    $this->actingAs($shooter);

    Livewire::test(ApplyForMembership::class, ['organisation' => $club])
        ->set('phone', '082 000 1111')
        ->set('category', ClubMemberCategory::Senior->value)
        ->set('emergencyContactName', 'Pieter van der Merwe')
        ->set('emergencyContactPhone', '082 000 2222')
        ->set('consent', true)
        ->call('apply')
        ->assertHasNoErrors()
        ->assertSet('submitted', true);

    $member = ClubMember::query()->sole();
    expect($member->organisation_id)->toBe($club->id)
        ->and($member->user_id)->toBe($shooter->id)
        ->and($member->first_name)->toBe('Jana')
        ->and($member->last_name)->toBe('van der Merwe')
        ->and($member->status)->toBe(ClubMemberStatus::Applied)
        ->and($member->popia_consented_at)->not->toBeNull()
        ->and($member->statusChanges()->count())->toBe(1);

    Mail::assertQueued(ClubApplicationReceivedMail::class, fn (ClubApplicationReceivedMail $m): bool => $m->hasTo($secretary->email));
    Mail::assertNotQueued(ClubApplicationReceivedMail::class, fn (ClubApplicationReceivedMail $m): bool => $m->hasTo($editor->email));
});

it('refuses to apply when the club is closed', function () {
    $club = Organisation::factory()->create([
        'membership_enabled' => true,
        'accepts_applications' => false,
    ]);

    $this->actingAs(User::factory()->create());

    Livewire::test(ApplyForMembership::class, ['organisation' => $club])
        ->set('phone', '082 000 0000')
        ->set('emergencyContactName', 'Someone')
        ->set('emergencyContactPhone', '082 999')
        ->set('consent', true)
        ->call('apply')
        ->assertHasErrors(['phone']);

    expect(ClubMember::query()->count())->toBe(0);
    Mail::assertNothingQueued();
});

it('rejects an application without consent', function () {
    $club = makeOpenClub();
    $this->actingAs(User::factory()->create());

    Livewire::test(ApplyForMembership::class, ['organisation' => $club])
        ->set('phone', '082 111 2222')
        ->set('emergencyContactName', 'Someone')
        ->set('emergencyContactPhone', '082 999')
        ->set('consent', false)
        ->call('apply')
        ->assertHasErrors(['consent']);

    expect(ClubMember::query()->count())->toBe(0);
});

it('shows the existing status when a shooter re-opens the form', function () {
    $club = makeOpenClub();
    $shooter = User::factory()->create();

    ClubMember::factory()
        ->for($club, 'organisation')
        ->create([
            'user_id' => $shooter->id,
            'email' => $shooter->email,
            'status' => ClubMemberStatus::Active,
        ]);

    $this->actingAs($shooter);

    Livewire::test(ApplyForMembership::class, ['organisation' => $club])
        ->assertSee('already a member');
});

it('revives a resigned record as a fresh Applied entry', function () {
    $club = makeOpenClub();
    $shooter = User::factory()->create();

    $existing = ClubMember::factory()
        ->for($club, 'organisation')
        ->create([
            'user_id' => $shooter->id,
            'email' => $shooter->email,
            'status' => ClubMemberStatus::Resigned,
        ]);

    $this->actingAs($shooter);

    Livewire::test(ApplyForMembership::class, ['organisation' => $club])
        ->set('phone', '082 000 1111')
        ->set('emergencyContactName', 'Someone')
        ->set('emergencyContactPhone', '082 000 2222')
        ->set('consent', true)
        ->call('apply')
        ->assertSet('submitted', true);

    $existing->refresh();
    expect($existing->status)->toBe(ClubMemberStatus::Applied)
        ->and(ClubMember::query()->count())->toBe(1);
});
