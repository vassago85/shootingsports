<?php

namespace Database\Seeders;

use App\Enums\BillingInterval;
use App\Enums\ClubMemberCategory;
use App\Enums\ClubMemberStatus;
use App\Enums\EntryCollection;
use App\Enums\EventKind;
use App\Enums\EventStatus;
use App\Enums\MembershipInvoicePaymentMethod;
use App\Enums\MembershipInvoiceStatus;
use App\Enums\MembershipStatus;
use App\Enums\OrganisationKind;
use App\Enums\OrganisationUserRole;
use App\Enums\PlatformSubscriptionStatus;
use App\Enums\SubscriptionTier;
use App\Models\Article;
use App\Models\ClubGuestVisit;
use App\Models\ClubMember;
use App\Models\ClubPayable;
use App\Models\Event;
use App\Models\MembershipInvoice;
use App\Models\Organisation;
use App\Models\OrganisationMembershipFee;
use App\Models\OrganisationUser;
use App\Models\PlatformSubscription;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;
use RuntimeException;

/**
 * Fixture data for the Playwright browser suite only.
 * Refuses to run unless the connection is the dedicated sqlite file.
 */
class PlaywrightSeeder extends Seeder
{
    public function run(): void
    {
        $database = (string) config('database.connections.sqlite.database');

        if (config('database.default') !== 'sqlite' || ! str_contains($database, 'playwright.sqlite')) {
            throw new RuntimeException('PlaywrightSeeder only runs against database/playwright.sqlite.');
        }

        $this->call(DisciplineSeeder::class);

        $shooter = User::factory()->create([
            'name' => 'Playwright Shooter',
            'email' => 'playwright@shootingsports.test',
            'password' => 'password',
        ]);

        Event::factory()->confirmed()->create([
            'slug' => 'playwright-training-day',
            'title' => 'Playwright Training Day',
            'kind' => EventKind::Training,
            'starts_at' => now()->addDays(3),
        ]);

        Event::factory()->create([
            'slug' => 'playwright-club-match',
            'title' => 'Playwright Club Match',
            'kind' => EventKind::Competition,
            'status' => EventStatus::EntriesOpen,
            'confirmed_at' => now(),
            'accepts_platform_entries' => true,
            'entry_collection' => EntryCollection::External,
            'starts_at' => now()->addDays(10),
        ]);

        $author = User::factory()->create([
            'name' => 'Playwright Media',
            'email' => 'playwright-media@shootingsports.test',
            'is_media_partner' => true,
        ]);

        Article::query()->create([
            'user_id' => $author->id,
            'title' => 'Playwright range report',
            'slug' => 'playwright-range-report',
            'excerpt' => 'A Saturday on steel.',
            'body' => 'The squad finished in the wind.',
            'event_kinds' => [EventKind::Competition->value],
            'published_at' => now(),
        ]);

        $this->seedMembershipManagerFixtures($shooter);
        $this->seedPlatformBillingFixtures($shooter);
    }

    /**
     * Platform-billing fixtures: trial club, subaccount club (with an
     * issued invoice for the Playwright shooter), fresh club without a
     * subaccount, bank-transfer club and a tier-overage club.
     */
    protected function seedPlatformBillingFixtures(User $shooter): void
    {
        $trialAdmin = User::factory()->create([
            'name' => 'Trial Admin',
            'email' => 'trial-admin@shootingsports.test',
            'password' => 'password',
        ]);
        $trialClub = Organisation::factory()->create([
            'name' => 'Trial Club',
            'town' => 'Pretoria',
            'kind' => OrganisationKind::Club,
            'payout_method' => 'paystack_subaccount',
        ]);
        $this->attachAdminApproved($trialClub, $trialAdmin);
        PlatformSubscription::factory()->for($trialClub)->trial()->create([
            'tier' => SubscriptionTier::Micro,
            'interval' => BillingInterval::Monthly,
            'trial_ends_at' => CarbonImmutable::now()->addDays(14),
        ]);

        $subaccountAdmin = User::factory()->create([
            'name' => 'Subaccount Admin',
            'email' => 'subaccount-admin@shootingsports.test',
            'password' => 'password',
        ]);
        $subaccountClub = Organisation::factory()->create([
            'name' => 'Subaccount Club',
            'paystack_subaccount_id' => 'ACCT_playwright',
            'paystack_subaccount_status' => 'active',
            'agency_agreement_signed_at' => now(),
            'agency_agreement_version' => config('platform.agency_agreement_version'),
            'payout_method' => 'paystack_subaccount',
        ]);
        $this->attachAdminApproved($subaccountClub, $subaccountAdmin);
        PlatformSubscription::factory()->for($subaccountClub)->create([
            'status' => PlatformSubscriptionStatus::Active,
            'tier' => SubscriptionTier::Standard,
        ]);

        $noSubaccountAdmin = User::factory()->create([
            'name' => 'Fresh Club Admin',
            'email' => 'nosubaccount-admin@shootingsports.test',
            'password' => 'password',
        ]);
        $noSubaccountClub = Organisation::factory()->create([
            'name' => 'Fresh Club',
            'payout_method' => 'paystack_subaccount',
        ]);
        $this->attachAdminApproved($noSubaccountClub, $noSubaccountAdmin);
        PlatformSubscription::factory()->for($noSubaccountClub)->create([
            'status' => PlatformSubscriptionStatus::Active,
            'tier' => SubscriptionTier::Micro,
        ]);

        $member = ClubMember::factory()->active()->for($subaccountClub, 'organisation')->create([
            'user_id' => $shooter->id,
            'first_name' => 'Sam',
            'last_name' => 'Subaccount',
            'email' => $shooter->email,
            'expires_at' => now()->addYears(2)->toDateString(),
        ]);
        MembershipInvoice::factory()->create([
            'organisation_id' => $subaccountClub->id,
            'club_member_id' => $member->id,
            'amount_cents' => 60000,
            'period_start' => today()->startOfYear()->toDateString(),
            'period_end' => today()->endOfYear()->toDateString(),
        ]);

        $bankTransferAdmin = User::factory()->create([
            'name' => 'Bank Transfer Admin',
            'email' => 'bank-transfer-admin@shootingsports.test',
            'password' => 'password',
        ]);
        $bankTransferClub = Organisation::factory()->create([
            'name' => 'Bank Transfer Club',
            'payout_method' => 'bank_transfer',
        ]);
        $this->attachAdminApproved($bankTransferClub, $bankTransferAdmin);
        ClubPayable::factory()->for($bankTransferClub, 'organisation')->count(3)->create([
            'net_due_cents' => 20000,
            'paid_out' => false,
            'occurred_at' => now()->subDays(2),
        ]);

        $overageAdmin = User::factory()->create([
            'name' => 'Overage Admin',
            'email' => 'overage-admin@shootingsports.test',
            'password' => 'password',
        ]);
        $overageClub = Organisation::factory()->create([
            'name' => 'Overage Club',
            'active_member_count' => 65,
            'tier_grace_ends_at' => today()->addDays(14),
        ]);
        $this->attachAdminApproved($overageClub, $overageAdmin);
        PlatformSubscription::factory()->for($overageClub)->create([
            'status' => PlatformSubscriptionStatus::Active,
            'tier' => SubscriptionTier::Micro,
        ]);
    }

    private function attachAdminApproved(Organisation $organisation, User $user): void
    {
        $organisation->users()->attach($user->id, [
            'role' => OrganisationUserRole::Admin->value,
            'status' => MembershipStatus::Approved->value,
            'granted_at' => now(),
            'approved_at' => now(),
        ]);
    }

    /**
     * One club with membership admin, a Desk-user acting as Admin,
     * plus three members (active, expiring soon, lapsed) so Phase 1
     * renewals specs have something to point at.
     */
    protected function seedMembershipManagerFixtures(User $shooter): void
    {
        $admin = User::factory()->create([
            'name' => 'Playwright Secretary',
            'email' => 'playwright-secretary@shootingsports.test',
            'password' => 'password',
        ]);

        $club = Organisation::factory()->create([
            'slug' => 'playwright-rifle-club',
            'name' => 'Playwright Rifle Club',
            'membership_enabled' => true,
            'accepts_applications' => true,
            'membership_year_end_month' => 12,
            'membership_year_end_day' => 31,
            'membership_online_payments' => true,
            'membership_eft_instructions' => "Bank: Example\nAcc: 12345\nRef: your member number",
        ]);

        OrganisationMembershipFee::create([
            'organisation_id' => $club->id,
            'category' => ClubMemberCategory::Senior,
            'amount_cents' => 60000,
            'active' => true,
        ]);
        OrganisationMembershipFee::create([
            'organisation_id' => $club->id,
            'category' => ClubMemberCategory::Junior,
            'amount_cents' => 20000,
            'active' => true,
        ]);

        $year = (int) CarbonImmutable::today()->year;

        OrganisationUser::create([
            'organisation_id' => $club->id,
            'user_id' => $admin->id,
            'role' => OrganisationUserRole::Admin->value,
            'status' => MembershipStatus::Approved->value,
            'granted_at' => now(),
            'approved_at' => now(),
        ]);

        // Link Alice to the Playwright Shooter account so Phase 3's
        // My memberships surface has a real linked record to show,
        // and give her one open invoice so the self-service renew
        // flow has something to click.
        $alice = ClubMember::factory()->for($club, 'organisation')->create([
            'user_id' => $shooter->id,
            'first_name' => 'Alice',
            'last_name' => 'Active',
            'email' => $shooter->email,
            'category' => ClubMemberCategory::Senior,
            'status' => ClubMemberStatus::Active,
            'joined_at' => now()->subYears(2)->toDateString(),
            'expires_at' => now()->addMonths(10)->toDateString(),
        ]);

        MembershipInvoice::create([
            'organisation_id' => $club->id,
            'club_member_id' => $alice->id,
            'period_start' => CarbonImmutable::create($year, 1, 1)->toDateString(),
            'period_end' => CarbonImmutable::create($year, 12, 31)->toDateString(),
            'amount_cents' => 60000,
            'status' => MembershipInvoiceStatus::Issued,
            'issued_at' => now(),
            'due_at' => now()->addDays(14),
        ]);

        $edward = ClubMember::factory()->for($club, 'organisation')->create([
            'first_name' => 'Edward',
            'last_name' => 'Expiring',
            'email' => 'edward@example.test',
            'category' => ClubMemberCategory::Senior,
            'status' => ClubMemberStatus::Active,
            'joined_at' => now()->subYear()->toDateString(),
            'expires_at' => now()->addDays(10)->toDateString(),
        ]);

        // One open invoice so Phase 2 Playwright specs can mark it
        // paid without first having to issue one.
        MembershipInvoice::create([
            'organisation_id' => $club->id,
            'club_member_id' => $edward->id,
            'period_start' => CarbonImmutable::create($year, 1, 1)->toDateString(),
            'period_end' => CarbonImmutable::create($year, 12, 31)->toDateString(),
            'amount_cents' => 60000,
            'status' => MembershipInvoiceStatus::Issued,
            'issued_at' => now(),
            'due_at' => now()->addDays(30),
        ]);

        ClubMember::factory()->for($club, 'organisation')->create([
            'first_name' => 'Lisa',
            'last_name' => 'Lapsed',
            'email' => 'lisa@example.test',
            'category' => ClubMemberCategory::Senior,
            'status' => ClubMemberStatus::Active,
            'joined_at' => now()->subYears(3)->toDateString(),
            'expires_at' => now()->subMonths(2)->toDateString(),
        ]);

        // One paid historical invoice so the Phase 5 revenue tile shows
        // something non-zero when the Playwright report-page spec hits it.
        $paidMember = ClubMember::factory()->for($club, 'organisation')->create([
            'first_name' => 'Patricia',
            'last_name' => 'Paid',
            'email' => 'pat@example.test',
            'category' => ClubMemberCategory::Senior,
            'status' => ClubMemberStatus::Active,
            'joined_at' => now()->subYear()->toDateString(),
            'expires_at' => CarbonImmutable::create($year, 12, 31)->toDateString(),
        ]);
        MembershipInvoice::create([
            'organisation_id' => $club->id,
            'club_member_id' => $paidMember->id,
            'period_start' => CarbonImmutable::create($year, 1, 1)->toDateString(),
            'period_end' => CarbonImmutable::create($year, 12, 31)->toDateString(),
            'amount_cents' => 60000,
            'status' => MembershipInvoiceStatus::Paid,
            'issued_at' => CarbonImmutable::create($year, 2, 1),
            'paid_at' => CarbonImmutable::create($year, 2, 15),
            'payment_method' => MembershipInvoicePaymentMethod::Eft,
        ]);

        // Phase 4 fixtures — one custom field on the club and one
        // walkup guest to convert into an Applied member.
        $club->forceFill([
            'membership_custom_fields' => [
                ['key' => 'sa_id', 'label' => 'SA ID', 'type' => 'text', 'required' => false],
            ],
        ])->save();

        ClubGuestVisit::create([
            'organisation_id' => $club->id,
            'name' => 'Grace Guest',
            'email' => 'grace.guest@example.test',
            'phone' => '+27 11 000 0000',
            'visit_date' => now()->subDays(2)->toDateString(),
        ]);
    }
}
