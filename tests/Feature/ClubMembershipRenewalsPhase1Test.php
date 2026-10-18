<?php

use App\Enums\ClubMemberStatus;
use App\Enums\OrganisationUserRole;
use App\Filament\Desk\Resources\ClubMembers\Pages\ListClubMembers;
use App\Mail\ClubMembershipRenewalReminder;
use App\Models\ClubMember;
use App\Models\Organisation;
use App\Models\OrganisationUser;
use App\Models\User;
use Carbon\CarbonImmutable;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;

function attachMembershipRole(User $user, Organisation $organisation, OrganisationUserRole $role): void
{
    OrganisationUser::create([
        'organisation_id' => $organisation->id,
        'user_id' => $user->id,
        'role' => $role->value,
        'granted_at' => now(),
    ]);
}

beforeEach(function () {
    Filament::setCurrentPanel('desk');
});

it('lapses active members whose expiry has passed and writes an audit entry', function () {
    $expired = ClubMember::factory()->expired()->create();
    $future = ClubMember::factory()->active()->create();

    $this->artisan('club-members:lapse')->assertSuccessful();

    expect($expired->fresh()->status)->toBe(ClubMemberStatus::Lapsed)
        ->and($future->fresh()->status)->toBe(ClubMemberStatus::Active);

    $change = $expired->statusChanges()->latest('id')->first();
    expect($change)->not->toBeNull()
        ->and($change->from_status)->toBe(ClubMemberStatus::Active)
        ->and($change->to_status)->toBe(ClubMemberStatus::Lapsed)
        ->and($change->reason)->toBe('expired');
});

it('is a no-op the second time the sweep runs the same day', function () {
    ClubMember::factory()->expired()->create();

    $this->artisan('club-members:lapse')->assertSuccessful();
    $this->artisan('club-members:lapse')->expectsOutputToContain('0 members lapsed')->assertSuccessful();
});

it('sends a renewal reminder once per threshold', function () {
    Mail::fake();

    $organisation = Organisation::factory()->create([
        'membership_enabled' => true,
    ]);

    $member = ClubMember::factory()
        ->for($organisation, 'organisation')
        ->active()
        ->create([
            'email' => 'member@example.com',
            'expires_at' => now()->addDays(6)->toDateString(),
        ]);

    $this->artisan('club-members:send-renewal-reminders')->assertSuccessful();

    Mail::assertQueued(ClubMembershipRenewalReminder::class, 1);
    Mail::assertQueued(ClubMembershipRenewalReminder::class, fn (ClubMembershipRenewalReminder $m): bool => $m->member->is($member) && $m->daysUntilExpiry === 7);

    $this->artisan('club-members:send-renewal-reminders')->assertSuccessful();
    Mail::assertQueued(ClubMembershipRenewalReminder::class, 1);

    expect($member->fresh()->reminder_7_sent_at)->not->toBeNull();
});

it('escalates to the next threshold without re-sending the previous', function () {
    Mail::fake();

    $organisation = Organisation::factory()->create([
        'membership_enabled' => true,
    ]);

    $member = ClubMember::factory()
        ->for($organisation, 'organisation')
        ->active()
        ->create([
            'email' => 'member@example.com',
            'expires_at' => now()->addDays(28)->toDateString(),
            'reminder_30_sent_at' => now()->subDay(),
        ]);

    $this->artisan('club-members:send-renewal-reminders')->assertSuccessful();
    Mail::assertNotQueued(ClubMembershipRenewalReminder::class);

    $member->forceFill(['expires_at' => now()->addDays(13)->toDateString()])->save();

    $this->artisan('club-members:send-renewal-reminders')->assertSuccessful();
    Mail::assertQueued(ClubMembershipRenewalReminder::class, 1);
    Mail::assertQueued(
        ClubMembershipRenewalReminder::class,
        fn (ClubMembershipRenewalReminder $m): bool => $m->daysUntilExpiry === 14,
    );
});

it('skips reminders for Lapsed, Resigned, Suspended, Rejected and Applied members', function () {
    Mail::fake();

    $organisation = Organisation::factory()->create([
        'membership_enabled' => true,
    ]);

    foreach ([ClubMemberStatus::Lapsed, ClubMemberStatus::Resigned, ClubMemberStatus::Suspended, ClubMemberStatus::Rejected, ClubMemberStatus::Applied] as $status) {
        ClubMember::factory()
            ->for($organisation, 'organisation')
            ->create([
                'status' => $status,
                'email' => fake()->unique()->safeEmail(),
                'expires_at' => now()->addDays(5)->toDateString(),
            ]);
    }

    $this->artisan('club-members:send-renewal-reminders')->assertSuccessful();
    Mail::assertNotQueued(ClubMembershipRenewalReminder::class);
});

it('renew() sets Active, bumps expires_at and clears reminder stamps', function () {
    $organisation = Organisation::factory()->create([
        'membership_year_end_month' => 12,
        'membership_year_end_day' => 31,
    ]);

    $member = ClubMember::factory()
        ->for($organisation, 'organisation')
        ->create([
            'status' => ClubMemberStatus::Lapsed,
            'expires_at' => now()->subMonth()->toDateString(),
            'reminder_30_sent_at' => now()->subMonth(),
            'reminder_14_sent_at' => now()->subMonth(),
            'reminder_7_sent_at' => now()->subMonth(),
        ]);

    $yearEnd = $organisation->membershipYearEndFor();
    $member->renew($yearEnd);

    $member->refresh();
    expect($member->status)->toBe(ClubMemberStatus::Active)
        ->and($member->expires_at?->toDateString())->toBe($yearEnd->toDateString())
        ->and($member->reminder_30_sent_at)->toBeNull()
        ->and($member->reminder_14_sent_at)->toBeNull()
        ->and($member->reminder_7_sent_at)->toBeNull();
});

it('the Desk bulk renew action renews every selected member to the configured year-end', function () {
    $organisation = Organisation::factory()->create([
        'membership_year_end_month' => 12,
        'membership_year_end_day' => 31,
    ]);

    $admin = User::factory()->create();
    attachMembershipRole($admin, $organisation, OrganisationUserRole::Admin);

    $one = ClubMember::factory()->for($organisation, 'organisation')->expired()->create();
    $two = ClubMember::factory()->for($organisation, 'organisation')->expired()->create();

    $this->actingAs($admin);

    Livewire::test(ListClubMembers::class)
        ->callTableBulkAction('renew', [$one->id, $two->id]);

    $one->refresh();
    $two->refresh();
    $yearEnd = $organisation->membershipYearEndFor();

    expect($one->status)->toBe(ClubMemberStatus::Active)
        ->and($one->expires_at?->toDateString())->toBe($yearEnd->toDateString())
        ->and($two->status)->toBe(ClubMemberStatus::Active)
        ->and($two->expires_at?->toDateString())->toBe($yearEnd->toDateString());
});

it('Organisation::membershipYearEndFor returns next year once the configured date is past', function () {
    $organisation = Organisation::factory()->create([
        'membership_year_end_month' => 1,
        'membership_year_end_day' => 15,
    ]);

    $reference = CarbonImmutable::create((int) now()->year, 2, 1);
    $yearEnd = $organisation->membershipYearEndFor($reference);

    expect($yearEnd->year)->toBe($reference->year + 1)
        ->and($yearEnd->month)->toBe(1)
        ->and($yearEnd->day)->toBe(15);
});
