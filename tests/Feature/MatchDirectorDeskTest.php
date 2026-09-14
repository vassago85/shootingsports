<?php

use App\Enums\ListingStatus;
use App\Enums\OrganisationType;
use App\Enums\OrganisationUserRole;
use App\Models\Event;
use App\Models\Organisation;
use App\Models\User;
use App\Policies\OrganisationPolicy;

it('excludes upcoming events whose host listing is not published', function () {
    $draftHost = Organisation::factory()->create([
        'status' => ListingStatus::Pending,
        'name' => 'Draft Series Host',
    ]);
    Event::factory()->confirmed()->create([
        'title' => 'Hidden behind draft host',
        'host_organisation_id' => $draftHost->id,
        'starts_at' => now()->addWeek(),
    ]);
    Event::factory()->confirmed()->create([
        'title' => 'Public host match',
        'starts_at' => now()->addWeek(),
    ]);

    $titles = Event::query()->upcoming()->pluck('title');

    expect($titles)->toContain('Public host match')
        ->not->toContain('Hidden behind draft host');
});

it('allows any authenticated user to create an organisation listing', function () {
    $user = User::factory()->create();

    expect((new OrganisationPolicy)->create($user))->toBeTrue();
});

it('allows a match director to update their own listing', function () {
    $organisation = Organisation::factory()->create([
        'type' => OrganisationType::Series,
        'status' => ListingStatus::Pending,
    ]);
    $director = User::factory()->create();
    $organisation->users()->attach($director->id, [
        'role' => OrganisationUserRole::MatchDirector,
        'granted_at' => now(),
    ]);

    expect((new OrganisationPolicy)->update($director, $organisation))->toBeTrue();
});

it('denies a stranger from updating someone else\'s listing', function () {
    $organisation = Organisation::factory()->create();
    $stranger = User::factory()->create();

    expect((new OrganisationPolicy)->update($stranger, $organisation))->toBeFalse();
});

it('lets a registered user open the desk panel', function () {
    $user = User::factory()->create(['is_staff' => false]);

    $this->actingAs($user)
        ->get('/desk')
        ->assertOk();
});

it('blocks non-staff from the admin panel', function () {
    $user = User::factory()->create(['is_staff' => false]);

    $this->actingAs($user)
        ->get('/admin')
        ->assertForbidden();
});
