<?php

use App\Enums\MembershipStatus;
use App\Enums\OrganisationUserRole;
use App\Enums\PlatformSubscriptionStatus;
use App\Mail\TrialEndingReminder;
use App\Models\Organisation;
use App\Models\PlatformSubscription;
use App\Models\User;
use Illuminate\Support\Facades\Mail;

function attachOrganisationUser(Organisation $org, User $user, OrganisationUserRole $role, MembershipStatus $status = MembershipStatus::Approved): void
{
    $org->users()->attach($user->id, [
        'role' => $role->value,
        'status' => $status->value,
        'granted_at' => now(),
        'approved_at' => now(),
    ]);
}

function trialEndingIn(Organisation $org, int $days): PlatformSubscription
{
    return PlatformSubscription::factory()->for($org)->create([
        'status' => PlatformSubscriptionStatus::Trial,
        'trial_ends_at' => now()->addDays($days)->endOfDay(),
    ]);
}

it('mails trial reminders at T-5', function () {
    Mail::fake();
    $org = Organisation::factory()->create();
    $admin = User::factory()->create();
    attachOrganisationUser($org, $admin, OrganisationUserRole::Admin);
    trialEndingIn($org, 5);

    $this->artisan('subscriptions:notify-trial-ending')->assertSuccessful();

    Mail::assertSent(TrialEndingReminder::class, 1);
    Mail::assertSent(TrialEndingReminder::class, fn (TrialEndingReminder $mail): bool => $mail->hasTo($admin->email)
        && $mail->daysUntilTrialEnds === 5);
});

it('mails trial reminders at T-1', function () {
    Mail::fake();
    $org = Organisation::factory()->create();
    $secretary = User::factory()->create();
    attachOrganisationUser($org, $secretary, OrganisationUserRole::Secretary);
    trialEndingIn($org, 1);

    $this->artisan('subscriptions:notify-trial-ending')->assertSuccessful();

    Mail::assertSent(TrialEndingReminder::class, 1);
    Mail::assertSent(TrialEndingReminder::class, fn (TrialEndingReminder $mail): bool => $mail->hasTo($secretary->email)
        && $mail->daysUntilTrialEnds === 1);
});

it('renders subject and body copy for each offset', function (int $days, string $subject) {
    $subscription = trialEndingIn(Organisation::factory()->create(), $days);
    $mail = new TrialEndingReminder($subscription, $days, url('/desk/billing'));

    $mail->assertHasSubject($subject);
    $mail->assertSeeInHtml(url('/desk/billing'), false);
    $mail->assertSeeInHtml('read-only', false);
})->with([
    'T-5' => [5, 'Your ShootingSports trial ends in 5 days'],
    'T-1' => [1, 'Your ShootingSports trial ends tomorrow'],
    'T+0' => [0, 'Your ShootingSports trial ends today'],
]);

it('mails trial reminders at T+0 to admins and secretaries only', function () {
    Mail::fake();
    $org = Organisation::factory()->create();
    $admin = User::factory()->create();
    $secretary = User::factory()->create();
    attachOrganisationUser($org, $admin, OrganisationUserRole::Admin);
    attachOrganisationUser($org, $secretary, OrganisationUserRole::Secretary);
    attachOrganisationUser($org, User::factory()->create(), OrganisationUserRole::Editor);
    attachOrganisationUser($org, User::factory()->create(), OrganisationUserRole::MatchDirector);
    attachOrganisationUser($org, User::factory()->create(), OrganisationUserRole::Admin, MembershipStatus::Pending);
    trialEndingIn($org, 0);

    $this->artisan('subscriptions:notify-trial-ending')->assertSuccessful();

    Mail::assertSent(TrialEndingReminder::class, 2);
    Mail::assertSent(TrialEndingReminder::class, fn (TrialEndingReminder $mail): bool => $mail->hasTo($admin->email)
        && $mail->daysUntilTrialEnds === 0);
    Mail::assertSent(TrialEndingReminder::class, fn (TrialEndingReminder $mail): bool => $mail->hasTo($secretary->email));
});

it('ignores subscriptions that are not on an offset day or not in trial', function () {
    Mail::fake();
    $org = Organisation::factory()->create();
    attachOrganisationUser($org, User::factory()->create(), OrganisationUserRole::Admin);
    trialEndingIn($org, 3);

    $activeOrg = Organisation::factory()->create();
    attachOrganisationUser($activeOrg, User::factory()->create(), OrganisationUserRole::Admin);
    PlatformSubscription::factory()->for($activeOrg)->create([
        'status' => PlatformSubscriptionStatus::Active,
        'trial_ends_at' => now()->addDays(5)->endOfDay(),
    ]);

    $this->artisan('subscriptions:notify-trial-ending')->assertSuccessful();

    Mail::assertNothingSent();
});
