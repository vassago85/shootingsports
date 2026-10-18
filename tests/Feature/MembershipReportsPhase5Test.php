<?php

use App\Enums\ClubMemberCategory;
use App\Enums\ClubMemberStatus;
use App\Enums\MembershipInvoicePaymentMethod;
use App\Enums\MembershipInvoiceStatus;
use App\Enums\MembershipStatus;
use App\Enums\OrganisationUserRole;
use App\Filament\Desk\Pages\MembershipReports;
use App\Models\ClubMember;
use App\Models\MembershipInvoice;
use App\Models\Organisation;
use App\Models\OrganisationUser;
use App\Models\User;
use Carbon\CarbonImmutable;
use Livewire\Livewire;
use Symfony\Component\HttpFoundation\StreamedResponse;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->club = Organisation::factory()->create();
    OrganisationUser::create([
        'organisation_id' => $this->club->id,
        'user_id' => $this->user->id,
        'role' => OrganisationUserRole::Admin->value,
        'status' => MembershipStatus::Approved->value,
        'granted_at' => now(),
        'approved_at' => now(),
    ]);

    $this->actingAs($this->user);
});

it('counts members by status for clubs the current admin manages only', function () {
    $otherClub = Organisation::factory()->create();

    ClubMember::factory()->count(3)->create([
        'organisation_id' => $this->club->id,
        'status' => ClubMemberStatus::Active,
    ]);
    ClubMember::factory()->create([
        'organisation_id' => $this->club->id,
        'status' => ClubMemberStatus::Lapsed,
    ]);
    // Noise in another club the user does not manage.
    ClubMember::factory()->count(5)->create([
        'organisation_id' => $otherClub->id,
        'status' => ClubMemberStatus::Active,
    ]);

    Livewire::test(MembershipReports::class)
        ->assertSet('year', (string) CarbonImmutable::today()->year)
        ->call('statusBreakdown')
        ->assertReturned(function (array $breakdown): bool {
            return $breakdown[ClubMemberStatus::Active->value] === 3
                && $breakdown[ClubMemberStatus::Lapsed->value] === 1;
        });
});

it('sums only paid-this-year invoices in the revenue tile', function () {
    $year = (int) CarbonImmutable::today()->year;
    $member = ClubMember::factory()->active()->create(['organisation_id' => $this->club->id]);

    MembershipInvoice::create([
        'organisation_id' => $this->club->id,
        'club_member_id' => $member->id,
        'period_start' => CarbonImmutable::create($year, 1, 1)->toDateString(),
        'period_end' => CarbonImmutable::create($year, 12, 31)->toDateString(),
        'amount_cents' => 60000,
        'status' => MembershipInvoiceStatus::Paid,
        'issued_at' => CarbonImmutable::create($year, 2, 1),
        'paid_at' => CarbonImmutable::create($year, 2, 15),
        'payment_method' => MembershipInvoicePaymentMethod::Eft,
    ]);

    // Prior-year paid invoice must not be counted.
    MembershipInvoice::create([
        'organisation_id' => $this->club->id,
        'club_member_id' => $member->id,
        'period_start' => CarbonImmutable::create($year - 1, 1, 1)->toDateString(),
        'period_end' => CarbonImmutable::create($year - 1, 12, 31)->toDateString(),
        'amount_cents' => 55000,
        'status' => MembershipInvoiceStatus::Paid,
        'issued_at' => CarbonImmutable::create($year - 1, 2, 1),
        'paid_at' => CarbonImmutable::create($year - 1, 2, 15),
        'payment_method' => MembershipInvoicePaymentMethod::Eft,
    ]);

    // Issued (unpaid) does not count toward revenue, but does count toward outstanding.
    $otherMember = ClubMember::factory()->active()->create(['organisation_id' => $this->club->id]);
    MembershipInvoice::create([
        'organisation_id' => $this->club->id,
        'club_member_id' => $otherMember->id,
        'period_start' => CarbonImmutable::create($year, 1, 1)->toDateString(),
        'period_end' => CarbonImmutable::create($year, 12, 31)->toDateString(),
        'amount_cents' => 20000,
        'status' => MembershipInvoiceStatus::Issued,
        'issued_at' => now(),
    ]);

    Livewire::test(MembershipReports::class)
        ->call('revenueCentsForYear')->assertReturned(60000)
        ->call('outstandingCount')->assertReturned(1);
});

it('streams a CSV with one row per paid invoice this year', function () {
    $year = (int) CarbonImmutable::today()->year;
    $member = ClubMember::factory()->active()->create([
        'organisation_id' => $this->club->id,
        'first_name' => 'Rebecca',
        'last_name' => 'Reporter',
        'category' => ClubMemberCategory::Senior,
    ]);

    MembershipInvoice::create([
        'organisation_id' => $this->club->id,
        'club_member_id' => $member->id,
        'period_start' => CarbonImmutable::create($year, 1, 1)->toDateString(),
        'period_end' => CarbonImmutable::create($year, 12, 31)->toDateString(),
        'amount_cents' => 60000,
        'status' => MembershipInvoiceStatus::Paid,
        'issued_at' => CarbonImmutable::create($year, 2, 1),
        'paid_at' => CarbonImmutable::create($year, 2, 15),
        'payment_method' => MembershipInvoicePaymentMethod::Eft,
    ]);

    $component = Livewire::test(MembershipReports::class);
    /** @var StreamedResponse $response */
    $response = $component->instance()->downloadRevenueCsv();

    ob_start();
    $response->sendContent();
    $csv = ob_get_clean();

    expect($csv)->toContain('Rebecca Reporter')
        ->and($csv)->toContain('600.00')
        ->and(substr_count($csv, "\n"))->toBe(2); // header + 1 row
});
