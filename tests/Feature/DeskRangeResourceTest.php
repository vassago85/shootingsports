<?php

use App\Enums\MembershipRole;
use App\Enums\MembershipStatus;
use App\Filament\Desk\Resources\Venues\VenueResource;
use App\Models\User;
use App\Models\Venue;
use App\Models\VenueMembership;
use App\Support\DeskCapabilities;
use Filament\Facades\Filament;

/*
 * Range desk resource — mirrors the Organisations and Providers desk
 * surfaces for the venue_user capability. A range owner should see
 * their own ranges, be able to edit them, be unable to edit anyone
 * else's, and should not see the resource at all when they have no
 * approved venue_user membership.
 */

beforeEach(function (): void {
    $this->withoutVite();
    config()->set('coming-soon.enabled', false);
});

function makeRangeOwner(Venue $range): User
{
    $owner = User::factory()->create();

    VenueMembership::query()->create([
        'venue_id' => $range->id,
        'user_id' => $owner->id,
        'role' => MembershipRole::Owner->value,
        'status' => MembershipStatus::Approved->value,
        'approved_at' => now(),
    ]);

    return $owner->fresh();
}

it('registers the Venues resource in the desk panel for a range owner', function () {
    $range = Venue::factory()->create();
    $owner = makeRangeOwner($range);

    Filament::setCurrentPanel('desk');
    Filament::auth()->login($owner);

    expect(VenueResource::canViewAny())->toBeTrue()
        ->and(VenueResource::shouldRegisterNavigation())->toBeTrue();
});

it('hides the Venues resource from a plain shooter', function () {
    $shooter = User::factory()->create();

    Filament::setCurrentPanel('desk');
    Filament::auth()->login($shooter);

    expect(VenueResource::canViewAny())->toBeFalse()
        ->and(VenueResource::shouldRegisterNavigation())->toBeFalse();
});

it('scopes the Venues list to the owner\'s approved memberships', function () {
    $myRange = Venue::factory()->create();
    $otherRange = Venue::factory()->create();
    $owner = makeRangeOwner($myRange);

    Filament::setCurrentPanel('desk');
    Filament::auth()->login($owner);

    $ids = VenueResource::getEloquentQuery()->pluck('venues.id')->all();

    expect($ids)->toContain($myRange->id)
        ->not->toContain($otherRange->id);
});

it('lets staff see every range in the desk Venues list', function () {
    $rangeA = Venue::factory()->create();
    $rangeB = Venue::factory()->create();
    $staff = User::factory()->staff()->create();

    Filament::setCurrentPanel('desk');
    Filament::auth()->login($staff);

    $ids = VenueResource::getEloquentQuery()->pluck('venues.id')->all();

    expect($ids)->toContain($rangeA->id)->toContain($rangeB->id);
});

it('points the Range capability card at the new desk/venues URL', function () {
    $range = Venue::factory()->create(['name' => 'Pretoria Precision Range']);
    $owner = makeRangeOwner($range);

    $cards = DeskCapabilities::for($owner);
    $rangeCard = collect($cards)->firstWhere('kind', 'range');

    expect($rangeCard)->not->toBeNull()
        ->and($rangeCard['primary_action']['url'])->toEndWith('/desk/venues')
        ->and($rangeCard['items'][0]['name'])->toBe('Pretoria Precision Range');
});

it('shows the Range capability card on /desk for a range-only user instead of the no-access landing', function () {
    $range = Venue::factory()->create(['name' => 'Welkom Pistol Club Range']);
    $owner = makeRangeOwner($range);

    Filament::setCurrentPanel('desk');

    $this->actingAs($owner)
        ->get('/desk')
        ->assertOk()
        ->assertSee('What you manage')
        ->assertSee('data-test="capability-card"', false)
        ->assertSee('Open ranges on the desk')
        ->assertDontSee('Your desk opens once you manage a listing');
});

it('serves the Venues index page to a range owner', function () {
    $range = Venue::factory()->create();
    $owner = makeRangeOwner($range);

    Filament::setCurrentPanel('desk');

    $this->actingAs($owner)
        ->get(VenueResource::getUrl('index'))
        ->assertOk()
        ->assertSee($range->name);
});

it('serves the Venues edit page for the owner\'s own range', function () {
    $range = Venue::factory()->create();
    $owner = makeRangeOwner($range);

    Filament::setCurrentPanel('desk');

    $this->actingAs($owner)
        ->get(VenueResource::getUrl('edit', ['record' => $range]))
        ->assertOk();
});
