<?php

use App\Enums\OrganisationUserRole;
use App\Models\Event;
use App\Models\Organisation;
use App\Models\User;
use App\Policies\EventPolicy;

it('denies update when the user holds no role on the host organisation', function () {
    $event = Event::factory()->create();
    $stranger = User::factory()->create();

    expect((new EventPolicy)->update($stranger, $event))->toBeFalse();
});

it('allows update when the user is a match director on the host organisation', function () {
    $organisation = Organisation::factory()->create();
    $director = User::factory()->create();
    $organisation->users()->attach($director->id, [
        'role' => OrganisationUserRole::MatchDirector,
        'granted_at' => now(),
    ]);
    $event = Event::factory()->create(['host_organisation_id' => $organisation->id]);

    expect((new EventPolicy)->update($director, $event))->toBeTrue();
});

it('allows staff to update any event', function () {
    $event = Event::factory()->create();
    $staff = User::factory()->staff()->create();

    expect((new EventPolicy)->update($staff, $event))->toBeTrue();
});
