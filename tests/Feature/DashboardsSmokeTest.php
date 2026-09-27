<?php

use App\Enums\EventStatus;
use App\Enums\ListingStatus;
use App\Enums\OrganisationUserRole;
use App\Enums\ProviderCategory;
use App\Filament\Pages\StaffDashboard;
use App\Models\Event;
use App\Models\Organisation;
use App\Models\Provider;
use App\Models\User;
use Filament\Facades\Filament;

beforeEach(function (): void {
    $this->withoutVite();
    config()->set('coming-soon.enabled', false);
});

// ---- Admin (staff) dashboard --------------------------------------------

it('loads the staff dashboard for a signed-in staff user', function () {
    $staff = User::factory()->staff()->create();

    $this->actingAs($staff)
        ->get(StaffDashboard::getUrl())
        ->assertOk()
        // Register stats block must render — this is the widget
        // that used to run its own count queries. If it silently
        // fails after the PublicCounts refactor the labels won't
        // show at all.
        ->assertSee('Upcoming matches')
        ->assertSee('Clubs')
        ->assertSee('Ranges')
        ->assertSee('Sports')
        ->assertSee('Industry')
        ->assertSee('Users')
        // Attention band + quick actions.
        ->assertSee('Needs attention')
        ->assertSee('Quick actions');
});

it('shuts a non-staff user out of the admin dashboard', function () {
    $shooter = User::factory()->create();

    $this->actingAs($shooter)
        ->get(StaffDashboard::getUrl())
        ->assertForbidden();
});

// ---- MD (desk) dashboard -------------------------------------------------

it('loads the match director desk for a signed-in match director', function () {
    $director = User::factory()->matchDirector()->create();

    // DeskDashboard::getUrl() resolves against the currently-active
    // panel, which defaults to /admin in tests. Switch to the desk
    // panel so the /desk URL resolves correctly.
    Filament::setCurrentPanel('desk');

    $this->actingAs($director)
        ->get('/desk')
        ->assertOk()
        ->assertSee('Match director desk')
        ->assertSee('Create club or series')
        ->assertSee('Claim a listing')
        ->assertSee('Add a match');
});

it('shuts a plain shooter out of the desk', function () {
    $shooter = User::factory()->create();

    Filament::setCurrentPanel('desk');

    // Filament redirects unauthenticated / unauthorised users to
    // the panel's login rather than serving a 403 — the assertion
    // covers both the redirect and the forbidden case.
    $response = $this->actingAs($shooter)->get('/desk');

    expect($response->status())->toBeIn([302, 403, 404]);
});

it('shows the desk stats and upcoming-matches widget once the director has a listing', function () {
    $director = User::factory()->matchDirector()->create();
    $club = Organisation::factory()->create([
        'name' => 'Dashboards Test Club',
        'status' => ListingStatus::Published,
        'claimed_by' => $director->id,
    ]);
    $club->users()->attach($director->id, [
        'role' => OrganisationUserRole::MatchDirector->value,
        'granted_at' => now(),
    ]);

    Event::factory()->create([
        'host_organisation_id' => $club->id,
        'title' => 'Dashboards Smoke Match',
        'status' => EventStatus::Confirmed,
        'starts_at' => now()->addWeek(),
        'ends_at' => now()->addWeek()->addHours(6),
    ]);
    Event::factory()->create([
        'host_organisation_id' => $club->id,
        'title' => 'Dashboards Draft Match',
        'status' => EventStatus::Draft,
        'starts_at' => now()->addWeeks(2),
        'ends_at' => now()->addWeeks(2)->addHours(6),
    ]);

    Filament::setCurrentPanel('desk');

    $this->actingAs($director)
        ->get('/desk')
        ->assertOk()
        ->assertSee('Upcoming')
        ->assertSee('Draft')
        ->assertSee('Next matches on your desk')
        ->assertSee('Dashboards Smoke Match');
});

// ---- Shooter my-calendar & my-log ---------------------------------------

it('loads the shooter my-calendar page for a signed-in user and shows the new nav', function () {
    $shooter = User::factory()->create();

    $this->actingAs($shooter)
        ->get(route('my-calendar'))
        ->assertOk()
        ->assertSee('My calendar')
        // The nav overhaul rendered via <x-public-nav>. If the nav
        // ever silently swaps back to the old inline markup this
        // assertion catches it because the Find hub only exists on
        // the new one.
        ->assertSee('Find', false);
});

it('loads the shooter my-log page for a signed-in user', function () {
    $shooter = User::factory()->create();

    $this->actingAs($shooter)
        ->get(route('my-log'))
        ->assertOk()
        ->assertSee('Attendance record');
});

// ---- Supplier onboarding "dashboard" -------------------------------------

it('loads the supplier onboarding form for a verified user with no listing yet', function () {
    $supplier = User::factory()->create(['supplier_requested_at' => now()]);

    $this->actingAs($supplier)
        ->get(route('suppliers.onboard'))
        ->assertOk();
});

it('sends a verified supplier with an existing listing to the thanks page', function () {
    $supplier = User::factory()->create(['supplier_requested_at' => now()]);
    $provider = Provider::factory()->create([
        'slug' => 'dashboards-smoke-onboarded',
        'name' => 'Onboarded Test Supplier',
        'category' => ProviderCategory::Dealer,
        'status' => ListingStatus::Pending,
        'claimed_by' => $supplier->id,
    ]);
    $supplier->providers()->save($provider);

    $this->actingAs($supplier)
        ->get(route('suppliers.onboard'))
        ->assertRedirect(route('suppliers.onboard.thanks', ['provider' => $provider->slug]));
});

it('loads the supplier thanks page and shows the update / view actions', function () {
    $supplier = User::factory()->create(['supplier_requested_at' => now()]);
    $provider = Provider::factory()->create([
        'slug' => 'dashboards-smoke-thanks',
        'name' => 'Thanks Page Supplier',
        'category' => ProviderCategory::Dealer,
        'status' => ListingStatus::Published,
        'claimed_by' => $supplier->id,
    ]);

    $this->actingAs($supplier)
        ->get(route('suppliers.onboard.thanks', ['provider' => $provider->slug]))
        ->assertOk()
        ->assertSee('Update your listing')
        ->assertSee('View public page');
});
