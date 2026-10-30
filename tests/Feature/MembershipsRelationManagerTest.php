<?php

use App\Enums\OrganisationUserRole;
use App\Enums\UserRole;
use App\Filament\Resources\Organisations\OrganisationResource;
use App\Filament\Resources\Organisations\RelationManagers\MembershipsRelationManager;
use App\Models\Organisation;
use App\Models\OrganisationUser;
use App\Models\User;
use Filament\Facades\Filament;
use Livewire\Livewire;

beforeEach(function (): void {
    $this->withoutVite();
    Filament::setCurrentPanel('admin');
});

/**
 * Regression: the memberships relation table used to crash with
 * "'user' is not a valid backing value for enum OrganisationUserRole"
 * whenever a club member was a plain shooter (users.role = 'user').
 * The TextColumn resolved `role` against the User record first,
 * triggering an enum cast against the wrong class. The column now
 * reads the pivot explicitly.
 */
it('renders the organisation memberships table when a member is a shooter', function (): void {
    $staff = User::factory()->staff()->create();
    $club = Organisation::factory()->create();

    $shooter = User::factory()->create([
        'role' => UserRole::User->value,
    ]);

    OrganisationUser::create([
        'organisation_id' => $club->id,
        'user_id' => $shooter->id,
        'role' => OrganisationUserRole::MatchDirector->value,
        'granted_at' => now(),
    ]);

    $this->actingAs($staff);

    Livewire::test(MembershipsRelationManager::class, [
        'ownerRecord' => $club,
        'pageClass' => OrganisationResource\Pages\EditOrganisation::class,
    ])
        ->assertCanSeeTableRecords([$shooter])
        // The column shows the pivot role (Match director), not the
        // shooter's site-wide UserRole label ("Shooter"). Guards against
        // regressing to reading `role` off the User record.
        ->assertSee(OrganisationUserRole::MatchDirector->getLabel())
        ->assertDontSee(UserRole::User->getLabel())
        ->assertSuccessful();
});
