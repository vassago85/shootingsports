<?php

use App\Enums\EnquiryStatus;
use App\Enums\EnquiryType;
use App\Enums\EventStatus;
use App\Enums\MembershipRole;
use App\Enums\MembershipStatus;
use App\Enums\OrganisationUserRole;
use App\Enums\PlacementSlot;
use App\Models\Enquiry;
use App\Models\Event;
use App\Models\Organisation;
use App\Models\OrganisationUser;
use App\Models\Placement;
use App\Models\Provider;
use App\Models\ProviderMembership;
use App\Models\User;
use App\Models\Venue;
use App\Models\VenueMembership;
use Illuminate\Database\Eloquent\Relations\Relation;

/*
 * Light manage endpoints for the mobile app. The shooter API is the
 * canonical surface; these routes only cover actions a desk user can
 * reasonably do from a phone (change event status, triage enquiries,
 * glance at advert stats). Heavier editing stays on the desk website.
 */

function bearer(User $user): string
{
    return 'Bearer '.$user->createToken('test')->plainTextToken;
}

it('exposes capabilities, limits, usage and plan on /api/v1/me', function (): void {
    $user = User::factory()->mediaPartner()->create();
    $club = Organisation::factory()->create();
    OrganisationUser::query()->create([
        'user_id' => $user->id,
        'organisation_id' => $club->id,
        'role' => OrganisationUserRole::Admin->value,
        'status' => MembershipStatus::Approved->value,
        'approved_at' => now(),
        'granted_at' => now(),
    ]);

    $this->withHeader('Authorization', bearer($user))
        ->getJson('/api/v1/me')
        ->assertOk()
        ->assertJsonStructure([
            'data' => [
                'id', 'name', 'email', 'plan', 'is_pro', 'on_trial',
                'capabilities', 'has_desk_access',
                'limits' => ['follows', 'saved_searches', 'attended_events_slots', 'history_months'],
                'usage' => ['follows', 'saved_searches', 'attended_events_slots'],
                'remaining' => ['follows', 'saved_searches', 'attended_events_slots'],
            ],
        ])
        ->assertJsonPath('data.has_desk_access', true)
        ->assertJsonPath('data.capabilities', ['club', 'media'])
        ->assertJsonPath('data.limits.follows', 3)
        ->assertJsonPath('data.remaining.follows', 3);
});

it('returns an empty capabilities list for a plain shooter', function (): void {
    $user = User::factory()->create();

    $this->withHeader('Authorization', bearer($user))
        ->getJson('/api/v1/me')
        ->assertOk()
        ->assertJsonPath('data.capabilities', [])
        ->assertJsonPath('data.has_desk_access', false);
});

it('returns the full capability cards from /api/v1/me/capabilities', function (): void {
    $user = User::factory()->create();
    $club = Organisation::factory()->create(['name' => 'Benoni PRC']);
    $shop = Provider::factory()->create(['name' => 'Vector Optics']);
    $range = Venue::factory()->create(['name' => 'Tulbagh Range']);

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

    $response = $this->withHeader('Authorization', bearer($user))
        ->getJson('/api/v1/me/capabilities')
        ->assertOk()
        ->assertJsonCount(3, 'data');

    $kinds = collect($response->json('data'))->pluck('kind')->all();
    expect($kinds)->toEqual(['club', 'shop', 'range']);
});

it('lists only events hosted by clubs the user manages', function (): void {
    $user = User::factory()->create();
    $myClub = Organisation::factory()->create();
    $otherClub = Organisation::factory()->create();

    OrganisationUser::query()->create([
        'user_id' => $user->id,
        'organisation_id' => $myClub->id,
        'role' => OrganisationUserRole::Admin->value,
        'status' => MembershipStatus::Approved->value,
        'approved_at' => now(),
        'granted_at' => now(),
    ]);

    $mine = Event::factory()->create([
        'host_organisation_id' => $myClub->id,
        'title' => 'My Managed Match',
        'starts_at' => now()->addWeek(),
    ]);
    Event::factory()->create([
        'host_organisation_id' => $otherClub->id,
        'title' => 'Not Mine',
        'starts_at' => now()->addWeek(),
    ]);

    $this->withHeader('Authorization', bearer($user))
        ->getJson('/api/v1/me/events')
        ->assertOk()
        ->assertJsonFragment(['title' => 'My Managed Match'])
        ->assertJsonMissing(['title' => 'Not Mine']);
});

it('lets a club admin update event status to a value the app allows', function (): void {
    $user = User::factory()->create();
    $club = Organisation::factory()->create();
    OrganisationUser::query()->create([
        'user_id' => $user->id,
        'organisation_id' => $club->id,
        'role' => OrganisationUserRole::Admin->value,
        'status' => MembershipStatus::Approved->value,
        'approved_at' => now(),
        'granted_at' => now(),
    ]);

    $event = Event::factory()->create([
        'host_organisation_id' => $club->id,
        'status' => EventStatus::Confirmed,
        'starts_at' => now()->addWeek(),
    ]);

    $this->withHeader('Authorization', bearer($user))
        ->patchJson("/api/v1/me/events/{$event->id}/status", ['status' => 'entries_open'])
        ->assertOk()
        ->assertJsonPath('status', 'entries_open');

    expect($event->fresh()->status)->toBe(EventStatus::EntriesOpen);
});

it('rejects an event status update from someone else\'s event', function (): void {
    $user = User::factory()->create();
    $someoneElsesClub = Organisation::factory()->create();

    $event = Event::factory()->create([
        'host_organisation_id' => $someoneElsesClub->id,
        'status' => EventStatus::Confirmed,
        'starts_at' => now()->addWeek(),
    ]);

    $this->withHeader('Authorization', bearer($user))
        ->patchJson("/api/v1/me/events/{$event->id}/status", ['status' => 'cancelled'])
        ->assertStatus(403);

    expect($event->fresh()->status)->toBe(EventStatus::Confirmed);
});

it('refuses to set draft or completed from the app', function (): void {
    $user = User::factory()->create();
    $club = Organisation::factory()->create();
    OrganisationUser::query()->create([
        'user_id' => $user->id,
        'organisation_id' => $club->id,
        'role' => OrganisationUserRole::Admin->value,
        'status' => MembershipStatus::Approved->value,
        'approved_at' => now(),
        'granted_at' => now(),
    ]);

    $event = Event::factory()->create([
        'host_organisation_id' => $club->id,
        'status' => EventStatus::Confirmed,
        'starts_at' => now()->addWeek(),
    ]);

    $this->withHeader('Authorization', bearer($user))
        ->patchJson("/api/v1/me/events/{$event->id}/status", ['status' => 'draft'])
        ->assertStatus(422);

    $this->withHeader('Authorization', bearer($user))
        ->patchJson("/api/v1/me/events/{$event->id}/status", ['status' => 'completed'])
        ->assertStatus(422);
});

it('lists only enquiries about listings the user manages', function (): void {
    $user = User::factory()->create();
    $myClub = Organisation::factory()->create(['name' => 'Mine Club']);
    $otherClub = Organisation::factory()->create();

    OrganisationUser::query()->create([
        'user_id' => $user->id,
        'organisation_id' => $myClub->id,
        'role' => OrganisationUserRole::Admin->value,
        'status' => MembershipStatus::Approved->value,
        'approved_at' => now(),
        'granted_at' => now(),
    ]);

    $mine = Enquiry::query()->create([
        'type' => EnquiryType::Listing,
        'name' => 'Jo Shooter',
        'email' => 'jo@example.test',
        'subject' => 'Open day?',
        'body' => 'When is your next open day for new members?',
        'about_type' => Relation::getMorphAlias(Organisation::class),
        'about_id' => $myClub->id,
        'status' => EnquiryStatus::New,
    ]);
    Enquiry::query()->create([
        'type' => EnquiryType::Listing,
        'name' => 'Not for me',
        'email' => 'nope@example.test',
        'subject' => 'For another club',
        'body' => 'This is about another club.',
        'about_type' => Relation::getMorphAlias(Organisation::class),
        'about_id' => $otherClub->id,
        'status' => EnquiryStatus::New,
    ]);

    $response = $this->withHeader('Authorization', bearer($user))
        ->getJson('/api/v1/me/enquiries')
        ->assertOk();

    $ids = collect($response->json('data'))->pluck('id')->all();
    expect($ids)->toEqual([$mine->id]);
});

it('returns advert stats only for shops the user owns', function (): void {
    $user = User::factory()->create();
    $myShop = Provider::factory()->create(['claimed_by' => $user->id]);
    $otherShop = Provider::factory()->create();

    Placement::query()->create([
        'provider_id' => $myShop->id,
        'slot' => PlacementSlot::CategorySponsor,
        'headline' => 'Mine Ad',
        'click_url' => 'https://example.test/mine',
        'starts_on' => now()->subDay()->toDateString(),
        'ends_on' => now()->addMonth()->toDateString(),
        'impressions' => 120,
        'clicks' => 9,
        'is_active' => true,
        'rate_cents' => 0,
    ]);
    Placement::query()->create([
        'provider_id' => $otherShop->id,
        'slot' => PlacementSlot::CategorySponsor,
        'headline' => 'Not Mine Ad',
        'click_url' => 'https://example.test/not-mine',
        'starts_on' => now()->subDay()->toDateString(),
        'ends_on' => now()->addMonth()->toDateString(),
        'impressions' => 400,
        'clicks' => 30,
        'is_active' => true,
        'rate_cents' => 0,
    ]);

    $response = $this->withHeader('Authorization', bearer($user))
        ->getJson('/api/v1/me/placements')
        ->assertOk();

    $headlines = collect($response->json('data'))->pluck('headline')->all();
    expect($headlines)->toEqual(['Mine Ad'])
        ->and((int) $response->json('data.0.impressions'))->toBe(120);
});

it('refuses capabilities, events, enquiries and placements without a token', function (): void {
    $this->getJson('/api/v1/me/capabilities')->assertStatus(401);
    $this->getJson('/api/v1/me/events')->assertStatus(401);
    $this->getJson('/api/v1/me/enquiries')->assertStatus(401);
    $this->getJson('/api/v1/me/placements')->assertStatus(401);
});
