<?php

use App\Enums\Plan;
use App\Livewire\Auth\ShooterRegister;
use App\Livewire\Upgrade;
use App\Mail\ProTrialEnded;
use App\Mail\ProTrialEndingSoon;
use App\Models\User;
use App\Services\StartProTrial;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;

beforeEach(function () {
    $this->withoutVite();
});

// ---- StartProTrial service ----------------------------------------

it('starts a 30-day Pro trial for an eligible Free user', function () {
    $user = User::factory()->create(['plan' => Plan::Free, 'plan_expires_at' => null]);

    expect(StartProTrial::isEligible($user))->toBeTrue();

    $result = StartProTrial::for($user);
    $user->refresh();

    expect($result)->toBeTrue()
        ->and($user->plan)->toBe(Plan::Pro)
        ->and($user->plan_expires_at)->not->toBeNull()
        ->and($user->plan_expires_at->isFuture())->toBeTrue()
        // End-of-day 30 days out (service contract). Compare dates,
        // not wall-clock hour counts — endOfDay makes diffInDays
        // round past 30 depending on when the suite runs.
        ->and($user->plan_expires_at->toDateString())
        ->toBe(now()->addDays(30)->toDateString())
        ->and($user->plan_expires_at->isEndOfDay())->toBeTrue()
        ->and($user->pro_trial_started_at)->not->toBeNull();
});

it('reports on-trial for the user during the trial window', function () {
    $user = User::factory()->create();
    StartProTrial::for($user);
    $user->refresh();

    expect($user->isOnTrial())->toBeTrue()
        ->and($user->isPro())->toBeTrue()
        ->and($user->effectivePlan())->toBe(Plan::Pro)
        ->and($user->hasActiveSubscription())->toBeFalse()
        ->and($user->hasHadTrial())->toBeTrue();
});

it('refuses to start a second trial for the same user', function () {
    $user = User::factory()->create();

    expect(StartProTrial::for($user))->toBeTrue();

    // Expire the first trial manually (simulate 30+ days passing).
    $user->forceFill(['plan_expires_at' => now()->subDay(), 'plan' => Plan::Free])->save();

    expect(StartProTrial::isEligible($user))->toBeFalse()
        ->and(StartProTrial::for($user))->toBeFalse();
});

it('refuses to start a trial for a user already on Pro', function () {
    $user = User::factory()->create([
        'plan' => Plan::Pro,
        'plan_expires_at' => now()->addYear(),
        'paystack_subscription_code' => 'SUB_abc',
    ]);

    expect(StartProTrial::isEligible($user))->toBeFalse()
        ->and(StartProTrial::for($user))->toBeFalse();
});

// ---- Effective plan auto-downgrades on trial expiry ---------------

it('degrades trial user to Free automatically once plan_expires_at passes', function () {
    $user = User::factory()->create();
    StartProTrial::for($user);

    // Simulate the 30 days elapsing.
    $user->forceFill(['plan_expires_at' => now()->subMinute()])->save();
    $user->refresh();

    expect($user->isPro())->toBeFalse()
        ->and($user->isOnTrial())->toBeFalse()
        ->and($user->effectivePlan())->toBe(Plan::Free);
});

// ---- Signup opt-in ------------------------------------------------

it('shooter signup with start_trial checked drops the new user straight onto Pro trial', function () {
    Livewire::test(ShooterRegister::class)
        ->set('name', 'Trialist Tim')
        ->set('email', 'tim@example.com')
        ->set('password', 'passw0rd!')
        ->set('password_confirmation', 'passw0rd!')
        ->set('start_trial', true)
        ->call('register');

    $user = User::where('email', 'tim@example.com')->firstOrFail();

    expect($user->isOnTrial())->toBeTrue()
        ->and($user->pro_trial_started_at)->not->toBeNull();
});

it('shooter signup with start_trial unchecked leaves the user on Free', function () {
    Livewire::test(ShooterRegister::class)
        ->set('name', 'Frugal Fred')
        ->set('email', 'fred@example.com')
        ->set('password', 'passw0rd!')
        ->set('password_confirmation', 'passw0rd!')
        ->set('start_trial', false)
        ->call('register');

    $user = User::where('email', 'fred@example.com')->firstOrFail();

    expect($user->isOnTrial())->toBeFalse()
        ->and($user->pro_trial_started_at)->toBeNull()
        ->and($user->effectivePlan())->toBe(Plan::Free);
});

// ---- /upgrade page --------------------------------------------------

it('/upgrade shows trial CTA for Free users who have not trialed yet', function () {
    $user = User::factory()->create(['plan' => Plan::Free, 'plan_expires_at' => null]);

    Livewire::actingAs($user)
        ->test(Upgrade::class)
        ->assertSet('selected', 'annual')
        ->assertSee('Start my 30-day trial')
        ->assertSee('no card required');
});

it('/upgrade hides the trial CTA once the user has used their trial', function () {
    $user = User::factory()->create();
    StartProTrial::for($user);
    // Simulate trial expiry so they're back on Free but hasHadTrial is true.
    $user->forceFill(['plan_expires_at' => now()->subDay()])->save();

    Livewire::actingAs($user->fresh())
        ->test(Upgrade::class)
        ->assertDontSee('Start my 30-day trial');
});

it('/upgrade shows trial progress banner while a trial is active', function () {
    $user = User::factory()->create();
    StartProTrial::for($user);

    Livewire::actingAs($user->fresh())
        ->test(Upgrade::class)
        ->assertSee("You're on Pro", false)
        ->assertSee('Trial ends');
});

it('/upgrade startTrial() action starts a fresh trial and redirects to /my-calendar', function () {
    $user = User::factory()->create(['plan' => Plan::Free, 'plan_expires_at' => null]);

    Livewire::actingAs($user)
        ->test(Upgrade::class)
        ->call('startTrial')
        ->assertRedirect('/my-calendar');

    expect($user->fresh()->isOnTrial())->toBeTrue();
});

it('/upgrade startTrial() refuses for a user who has already trialed', function () {
    $user = User::factory()->create();
    StartProTrial::for($user);
    $user->forceFill(['plan_expires_at' => now()->subDay()])->save();

    Livewire::actingAs($user->fresh())
        ->test(Upgrade::class)
        ->call('startTrial');

    // Second trial did not restart the window.
    expect($user->fresh()->isOnTrial())->toBeFalse();
});

// ---- Trial nudge command ------------------------------------------

it('pro:trial-nudges sends the T-3 email to eligible users only once', function () {
    Mail::fake();

    // Eligible: trial ending in 3 days, still on trial, no card, not yet nudged.
    $eligible = User::factory()->create();
    StartProTrial::for($eligible);
    $eligible->forceFill(['plan_expires_at' => now()->addDays(3)])->save();

    // Ineligible A: already nudged.
    $alreadyNudged = User::factory()->create();
    StartProTrial::for($alreadyNudged);
    $alreadyNudged->forceFill([
        'plan_expires_at' => now()->addDays(3),
        'pro_trial_ending_notified_at' => now()->subHour(),
    ])->save();

    // Ineligible B: trial ends in 10 days.
    $tooEarly = User::factory()->create();
    StartProTrial::for($tooEarly);
    $tooEarly->forceFill(['plan_expires_at' => now()->addDays(10)])->save();

    $this->artisan('pro:trial-nudges')->assertSuccessful();

    // ShouldQueue mailables land in the queued bucket under Mail::fake.
    Mail::assertQueued(ProTrialEndingSoon::class, fn ($mail) => $mail->user->is($eligible));
    Mail::assertQueued(ProTrialEndingSoon::class, 1);

    expect($eligible->fresh()->pro_trial_ending_notified_at)->not->toBeNull();
});

it('pro:trial-nudges sends the T+0 ended email once and stamps the user', function () {
    Mail::fake();

    $ended = User::factory()->create();
    StartProTrial::for($ended);
    $ended->forceFill(['plan_expires_at' => now()->subHour()])->save();

    $this->artisan('pro:trial-nudges')->assertSuccessful();

    Mail::assertQueued(ProTrialEnded::class, fn ($mail) => $mail->user->is($ended));
    Mail::assertQueued(ProTrialEnded::class, 1);

    expect($ended->fresh()->pro_trial_ended_notified_at)->not->toBeNull();

    // Second run must not re-send.
    Mail::fake();
    $this->artisan('pro:trial-nudges')->assertSuccessful();
    Mail::assertNothingQueued();
});

it('pro:trial-nudges skips users who opted out of trial nudges but still stamps them', function () {
    Mail::fake();

    $optOut = User::factory()->create(['emails_trial_nudges_enabled' => false]);
    StartProTrial::for($optOut);
    $optOut->forceFill(['plan_expires_at' => now()->addDays(3)])->save();

    $this->artisan('pro:trial-nudges')->assertSuccessful();

    Mail::assertNothingQueued();
    expect($optOut->fresh()->pro_trial_ending_notified_at)->not->toBeNull();
});
