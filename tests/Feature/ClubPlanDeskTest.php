<?php

use App\Enums\OrganisationUserRole;
use App\Filament\Desk\Resources\ClubMembers\Pages\ListClubMembers;
use App\Models\ClubMember;
use App\Models\Organisation;
use App\Models\OrganisationUser;
use App\Models\User;
use Filament\Facades\Filament;
use Livewire\Livewire;

beforeEach(function () {
    $this->withoutVite();
    Filament::setCurrentPanel('desk');
    config(['club-plans.enabled' => false]);
});

it('opens a lock page for a secretary whose club is not subscribed', function () {
    $club = Organisation::factory()->create(['name' => 'Unpaid Club']);
    $secretary = User::factory()->create();
    OrganisationUser::create([
        'organisation_id' => $club->id,
        'user_id' => $secretary->id,
        'role' => OrganisationUserRole::Secretary->value,
        'granted_at' => now(),
    ]);

    $this->actingAs($secretary);

    Livewire::test(ListClubMembers::class)
        ->assertSee('part of the club subscription')
        ->assertSee('Unpaid Club')
        ->assertSee('Ask about the club subscription')
        ->assertDontSee('Subscribe');
});

it('shows a subscribe link per unpaid club when club sales are on', function () {
    config(['club-plans.enabled' => true]);

    $unpaid = Organisation::factory()->clubPlan()->create(['name' => 'Locked Club']);
    $paid = Organisation::factory()->clubPlan()->create(['name' => 'Open Club']);
    $secretary = User::factory()->create();

    foreach ([$unpaid, $paid] as $club) {
        OrganisationUser::create([
            'organisation_id' => $club->id,
            'user_id' => $secretary->id,
            'role' => OrganisationUserRole::Secretary->value,
            'granted_at' => now(),
        ]);
    }

    ClubMember::factory()->for($paid, 'organisation')->create(['first_name' => 'PaidMember']);
    ClubMember::factory()->for($unpaid, 'organisation')->create(['first_name' => 'HiddenMember']);
    $unpaid->forceFill(['plan' => 'free', 'plan_expires_at' => null])->save();

    $this->actingAs($secretary);

    Livewire::test(ListClubMembers::class)
        ->assertSee('PaidMember')
        ->assertDontSee('HiddenMember')
        ->assertSee('Not on the club subscription: Locked Club');
});

it('hides the club subscribe page until sales are enabled', function () {
    $club = Organisation::factory()->create();
    $secretary = User::factory()->create();
    OrganisationUser::create([
        'organisation_id' => $club->id,
        'user_id' => $secretary->id,
        'role' => OrganisationUserRole::Secretary->value,
        'granted_at' => now(),
    ]);

    $this->actingAs($secretary)
        ->get(route('club-plan.show', $club))
        ->assertNotFound();

    config(['club-plans.enabled' => true]);

    $this->get(route('club-plan.show', $club))
        ->assertOk()
        ->assertSee('Pricing is not set yet');

    $this->actingAs(User::factory()->create())
        ->get(route('club-plan.show', $club))
        ->assertForbidden();
});
