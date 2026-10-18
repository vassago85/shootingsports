<?php

use App\Enums\EventPublication;
use App\Enums\EventStatus;
use App\Enums\ListingStatus;
use App\Enums\MembershipStatus;
use App\Enums\OrganisationUserRole;
use App\Enums\PlacementSlot;
use App\Enums\ProviderCategory;
use App\Enums\VerificationState;
use App\Models\Event;
use App\Models\Organisation;
use App\Models\OrganisationUser;
use App\Models\Placement;
use App\Models\Provider;
use App\Models\User;
use Filament\Facades\Filament;

beforeEach(function (): void {
    $this->withoutVite();
    config()->set('coming-soon.enabled', false);
    Filament::setCurrentPanel('desk');
});

it('shows the no-access landing to a plain shooter instead of a 403', function () {
    $shooter = User::factory()->create();

    $this->actingAs($shooter)
        ->get('/desk')
        ->assertOk()
        ->assertSee('Your desk opens once you manage a listing')
        ->assertSee('Claim a club')
        ->assertSee('List your business')
        ->assertSee('Go to my calendar');
});

it('shows the no-access landing to a pending match director', function () {
    $pending = User::factory()->create([
        'is_match_director' => false,
        'md_requested_at' => now(),
    ]);

    $this->actingAs($pending)
        ->get('/desk')
        ->assertOk()
        ->assertSee('Your desk opens once you manage a listing');
});

it('renders the director home with tiles, upcoming events and completeness', function () {
    $director = User::factory()->matchDirector()->create();
    $club = Organisation::factory()->create([
        'name' => 'Phase C Test Club',
        'status' => ListingStatus::Published,
        'verification_state' => VerificationState::Verified,
    ]);
    OrganisationUser::query()->create([
        'user_id' => $director->id,
        'organisation_id' => $club->id,
        'role' => OrganisationUserRole::MatchDirector->value,
        'status' => MembershipStatus::Approved->value,
        'approved_at' => now(),
        'granted_at' => now(),
    ]);

    Event::factory()->create([
        'host_organisation_id' => $club->id,
        'title' => 'Phase C Monthly Shoot',
        'status' => EventStatus::EntriesOpen,
        'publication' => EventPublication::Published,
        'starts_at' => now()->addWeek(),
        'ends_at' => now()->addWeek()->addHours(6),
        'venue_id' => null,
        'description' => null,
        'banner_path' => null,
        'entry_url' => null,
        'entry_fee_cents' => null,
        'accepts_platform_entries' => false,
    ]);

    Event::factory()->create([
        'host_organisation_id' => $club->id,
        'title' => 'Phase C Waiting Review',
        'status' => EventStatus::Draft,
        'publication' => EventPublication::PendingReview,
        'starts_at' => now()->addWeeks(2),
        'ends_at' => now()->addWeeks(2)->addHours(4),
    ]);

    $this->actingAs($director)
        ->get('/desk')
        ->assertOk()
        ->assertSee('Match director desk')
        ->assertSee('Welcome back')
        ->assertSee('Add event')
        ->assertSee('Clubs you manage')
        ->assertSee('Upcoming events')
        ->assertSee('Waiting for staff review')
        ->assertSee('Phase C Monthly Shoot')
        ->assertSee('Phase C Test Club')
        ->assertSee('How publishing works')
        ->assertSee('%');
});

it('renders the industry home with advert stats and listing health', function () {
    $supplier = User::factory()->create(['supplier_requested_at' => now()]);
    $provider = Provider::factory()->create([
        'name' => 'Phase C Optics',
        'category' => ProviderCategory::Dealer,
        'status' => ListingStatus::Published,
        'claimed_by' => $supplier->id,
        'logo_path' => null,
        'description' => 'We sell glass.',
        'email' => 'optics@example.test',
        'last_verified_at' => now()->subYear(),
    ]);

    Placement::query()->create([
        'provider_id' => $provider->id,
        'slot' => PlacementSlot::CategorySponsor,
        'headline' => 'Phase C Optics ad',
        'click_url' => 'https://example.test/optics',
        'starts_on' => now()->subDay()->toDateString(),
        'ends_on' => now()->addMonth()->toDateString(),
        'impressions' => 204,
        'clicks' => 16,
        'is_active' => true,
        'rate_cents' => 0,
    ]);

    $this->actingAs($supplier)
        ->get('/desk')
        ->assertOk()
        ->assertSee('Industry desk')
        ->assertSee('Phase C Optics')
        ->assertSee('Advert views')
        ->assertSee('204')
        ->assertSee('Advert clicks')
        ->assertSee('16')
        ->assertSee('Listing health')
        ->assertSee('Logo and photos')
        ->assertSee('Missing')
        ->assertSee('Confirm now')
        ->assertSee('Edit listing');
});

it('scopes desk events to the director own clubs with no staff bypass', function () {
    $staff = User::factory()->staff()->create();
    $otherClub = Organisation::factory()->create(['name' => 'Somebody Else Club']);
    Event::factory()->create([
        'host_organisation_id' => $otherClub->id,
        'title' => 'Hidden From Staff Desk',
        'starts_at' => now()->addWeek(),
    ]);

    $ownClub = Organisation::factory()->create(['name' => 'Staff Own Club']);
    OrganisationUser::query()->create([
        'user_id' => $staff->id,
        'organisation_id' => $ownClub->id,
        'role' => OrganisationUserRole::MatchDirector->value,
        'status' => MembershipStatus::Approved->value,
        'approved_at' => now(),
        'granted_at' => now(),
    ]);
    Event::factory()->create([
        'host_organisation_id' => $ownClub->id,
        'title' => 'Staff Own Match',
        'status' => EventStatus::Confirmed,
        'publication' => EventPublication::Published,
        'starts_at' => now()->addWeek(),
        'ends_at' => now()->addWeek()->addHours(4),
    ]);

    $this->actingAs($staff)
        ->get('/desk')
        ->assertOk()
        ->assertSee('Staff Own Match')
        ->assertDontSee('Hidden From Staff Desk');
});
