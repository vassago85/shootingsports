<?php

use App\Enums\MembershipStatus;
use App\Enums\OrganisationUserRole;
use App\Enums\SubscriptionTier;
use App\Filament\Desk\Pages\BillingDashboard;
use App\Livewire\Platform\EnablePaystackSubaccount;
use App\Models\Organisation;
use App\Models\PlatformFeeLine;
use App\Models\PlatformFeeStatement;
use App\Models\PlatformSubscription;
use App\Models\User;
use Livewire\Livewire;

beforeEach(function () {
    $this->withoutVite();
});

function makeApprovedClubAdmin(Organisation $organisation): User
{
    $user = User::factory()->create();
    $organisation->users()->attach($user->id, [
        'role' => OrganisationUserRole::Admin->value,
        'status' => MembershipStatus::Approved->value,
        'granted_at' => now(),
        'approved_at' => now(),
    ]);

    return $user;
}

it('the Billing group is visible on the Desk panel for an approved admin', function () {
    $club = Organisation::factory()->create();
    PlatformSubscription::factory()->create(['organisation_id' => $club->id]);
    $admin = makeApprovedClubAdmin($club);

    $this->actingAs($admin)
        ->get('/desk/billing')
        ->assertOk()
        ->assertSee('Billing dashboard');
});

it('non-managers cannot access the Billing dashboard', function () {
    $member = User::factory()->create();

    $this->actingAs($member)
        ->get('/desk/billing')
        ->assertForbidden();

    $this->actingAs($member)
        ->get('/desk/billing-fee-statements')
        ->assertForbidden();
});

it('the dashboard shows tier, usage bar, next billing date for managed clubs only', function () {
    $club = Organisation::factory()->create(['name' => 'Managed Marksmen', 'active_member_count' => 25]);
    $otherClub = Organisation::factory()->create(['name' => 'Unrelated Rifle Club']);
    PlatformSubscription::factory()->create([
        'organisation_id' => $club->id,
        'tier' => SubscriptionTier::Standard,
        'current_period_end' => '2026-11-30',
    ]);
    PlatformSubscription::factory()->create(['organisation_id' => $otherClub->id]);
    $admin = makeApprovedClubAdmin($club);

    $this->actingAs($admin)
        ->get('/desk/billing')
        ->assertOk()
        ->assertSee('Managed Marksmen')
        ->assertSee('Standard')
        ->assertSee('25 /')
        ->assertSee('30 Nov 2026')
        ->assertSee('Enable Paystack subaccount')
        ->assertDontSee('Unrelated Rifle Club');
});

it('the Paystack page renders the EnablePaystackSubaccount livewire for the correct org', function () {
    $club = Organisation::factory()->create();
    $otherClub = Organisation::factory()->create();
    $admin = makeApprovedClubAdmin($club);
    $otherClub->users()->attach($admin->id, [
        'role' => OrganisationUserRole::Admin->value,
        'status' => MembershipStatus::Approved->value,
        'granted_at' => now(),
        'approved_at' => now(),
    ]);

    $this->actingAs($admin)
        ->get('/desk/billing-paystack?organisation='.$otherClub->id)
        ->assertOk()
        ->assertSeeLivewire(EnablePaystackSubaccount::class)
        ->assertSee($otherClub->name);
});

it('the fee statements page lists statements for managed clubs only', function () {
    $club = Organisation::factory()->create(['name' => 'Managed Marksmen']);
    $otherClub = Organisation::factory()->create(['name' => 'Unrelated Rifle Club']);
    PlatformFeeStatement::factory()->create([
        'organisation_id' => $club->id,
        'period_start' => '2026-09-01',
        'period_end' => '2026-09-30',
        'total_fee_cents' => 12345,
        'issued_at' => now(),
    ]);
    PlatformFeeStatement::factory()->create(['organisation_id' => $otherClub->id]);
    $admin = makeApprovedClubAdmin($club);

    $this->actingAs($admin)
        ->get('/desk/billing-fee-statements')
        ->assertOk()
        ->assertSee('Managed Marksmen')
        ->assertSee('R123.45')
        ->assertDontSee('Unrelated Rifle Club');
});

it('the fee statements page shows an empty state when there are none', function () {
    $club = Organisation::factory()->create();
    $admin = makeApprovedClubAdmin($club);

    $this->actingAs($admin)
        ->get('/desk/billing-fee-statements')
        ->assertOk()
        ->assertSee('No fee statements yet');
});

it('a user can download a fee statement CSV', function () {
    $club = Organisation::factory()->create();
    $statement = PlatformFeeStatement::factory()->create([
        'organisation_id' => $club->id,
        'period_start' => '2026-09-01',
        'period_end' => '2026-09-30',
    ]);
    $line = PlatformFeeLine::factory()->create(['platform_fee_statement_id' => $statement->id]);
    $admin = makeApprovedClubAdmin($club);

    $response = $this->actingAs($admin)
        ->get(route('desk.fee-statements.download', $statement))
        ->assertOk();

    expect($response->headers->get('Content-Type'))->toContain('text/csv')
        ->and($response->headers->get('Content-Disposition'))->toContain('fee-statement-2026-09.csv')
        ->and($response->streamedContent())->toContain((string) $line->membership_invoice_id);
});

it('forbids downloading another club\'s fee statement', function () {
    $club = Organisation::factory()->create();
    $statement = PlatformFeeStatement::factory()->create();
    $admin = makeApprovedClubAdmin($club);

    $this->actingAs($admin)
        ->get(route('desk.fee-statements.download', $statement))
        ->assertForbidden();
});

it('the dashboard change-tier and cancel stubs notify without changing the subscription', function () {
    $club = Organisation::factory()->create();
    $subscription = PlatformSubscription::factory()->create(['organisation_id' => $club->id]);
    $admin = makeApprovedClubAdmin($club);
    $this->actingAs($admin);

    Livewire::test(BillingDashboard::class)
        ->call('changeTier', $club->id)
        ->assertNotified()
        ->call('cancelSubscription', $club->id)
        ->assertNotified();

    expect($subscription->refresh()->cancel_at_period_end)->toBeFalse();
});
