<?php

use App\Enums\EmailCategory;
use App\Livewire\Settings\NotificationPreferences;
use App\Models\User;
use App\Support\EmailPreferences;
use Livewire\Livewire;

beforeEach(function () {
    $this->withoutVite();
});

// ---- Unsubscribe token lifecycle ----------------------------------

it('mints a permanent unsubscribe_token on every new user', function () {
    $user = User::factory()->create();

    expect($user->unsubscribe_token)->not->toBeNull()
        ->and(strlen($user->unsubscribe_token))->toBeGreaterThanOrEqual(32);
});

it('does not rotate the unsubscribe_token on subsequent saves', function () {
    $user = User::factory()->create();
    $original = $user->unsubscribe_token;

    $user->name = 'new name';
    $user->save();

    expect($user->fresh()->unsubscribe_token)->toBe($original);
});

// ---- EmailPreferences::canSend gate -------------------------------

it('allows transactional email even after full unsubscribe', function () {
    $user = User::factory()->create(['emails_marketing_enabled' => false]);

    expect(EmailPreferences::canSend($user, EmailCategory::Transactional))->toBeTrue();
});

it('blocks every non-transactional category when master marketing is off', function () {
    $user = User::factory()->create([
        'emails_marketing_enabled' => false,
        'emails_match_alerts_enabled' => true,
        'emails_weekly_digest_enabled' => true,
        'emails_product_updates_enabled' => true,
        'emails_trial_nudges_enabled' => true,
    ]);

    foreach ([
        EmailCategory::MatchAlerts,
        EmailCategory::WeeklyDigest,
        EmailCategory::ProductUpdates,
        EmailCategory::TrialNudges,
    ] as $category) {
        expect(EmailPreferences::canSend($user, $category))->toBeFalse();
    }
});

it('respects per-category opt-out when master marketing is on', function () {
    $user = User::factory()->create([
        'emails_marketing_enabled' => true,
        'emails_match_alerts_enabled' => true,
        'emails_weekly_digest_enabled' => false,
        'emails_product_updates_enabled' => true,
        'emails_trial_nudges_enabled' => true,
    ]);

    expect(EmailPreferences::canSend($user, EmailCategory::MatchAlerts))->toBeTrue()
        ->and(EmailPreferences::canSend($user, EmailCategory::WeeklyDigest))->toBeFalse()
        ->and(EmailPreferences::canSend($user, EmailCategory::ProductUpdates))->toBeTrue();
});

// ---- One-click unsubscribe endpoint -------------------------------

it('one-click unsubscribe flips marketing off and stamps audit trail', function () {
    $user = User::factory()->create(['emails_marketing_enabled' => true]);

    $response = $this->get('/email/unsubscribe/'.$user->unsubscribe_token);

    $response->assertOk();
    $response->assertSee("You're unsubscribed", false);

    $user->refresh();

    expect($user->emails_marketing_enabled)->toBeFalse()
        ->and($user->unsubscribed_at)->not->toBeNull()
        ->and($user->unsubscribe_source)->toBe('email_link');
});

it('one-click unsubscribe is idempotent and does not overwrite the original timestamp', function () {
    $user = User::factory()->create(['emails_marketing_enabled' => true]);

    $this->get('/email/unsubscribe/'.$user->unsubscribe_token)->assertOk();
    $firstStamp = $user->fresh()->unsubscribed_at;

    // Sleep just enough to make a difference detectable if the code
    // did rewrite the stamp.
    $this->travel(2)->minutes();

    $this->get('/email/unsubscribe/'.$user->unsubscribe_token)->assertOk();

    expect($user->fresh()->unsubscribed_at->timestamp)->toBe($firstStamp->timestamp);
});

it('invalid unsubscribe token renders a neutral message without leaking existence', function () {
    $response = $this->get('/email/unsubscribe/'.str_repeat('x', 40));

    $response->assertOk();
    $response->assertSee("That link isn't valid", false);
});

it('resubscribe via the confirmation page restores the master flag', function () {
    $user = User::factory()->create(['emails_marketing_enabled' => true]);

    // First unsubscribe.
    $this->get('/email/unsubscribe/'.$user->unsubscribe_token);

    // Then resubscribe.
    $response = $this->post('/email/unsubscribe/'.$user->unsubscribe_token);
    $response->assertRedirect(route('email.unsubscribe', ['token' => $user->unsubscribe_token]));

    $user->refresh();

    expect($user->emails_marketing_enabled)->toBeTrue()
        ->and($user->unsubscribed_at)->toBeNull()
        ->and($user->unsubscribe_source)->toBeNull();
});

// ---- Preferences settings page ------------------------------------

it('/settings/notifications loads with the user\'s current values', function () {
    $user = User::factory()->create([
        'emails_marketing_enabled' => true,
        'emails_match_alerts_enabled' => false,
        'emails_weekly_digest_enabled' => true,
        'emails_product_updates_enabled' => false,
        'emails_trial_nudges_enabled' => true,
    ]);

    Livewire::actingAs($user)
        ->test(NotificationPreferences::class)
        ->assertSet('marketingMaster', true)
        ->assertSet('matchAlerts', false)
        ->assertSet('weeklyDigest', true)
        ->assertSet('productUpdates', false)
        ->assertSet('trialNudges', true);
});

it('/settings/notifications save() persists every checkbox', function () {
    $user = User::factory()->create();

    Livewire::actingAs($user)
        ->test(NotificationPreferences::class)
        ->set('marketingMaster', false)
        ->set('matchAlerts', false)
        ->set('weeklyDigest', true)
        ->set('productUpdates', false)
        ->set('trialNudges', true)
        ->call('save')
        ->assertHasNoErrors();

    $user->refresh();

    expect($user->emails_marketing_enabled)->toBeFalse()
        ->and($user->emails_match_alerts_enabled)->toBeFalse()
        ->and($user->emails_weekly_digest_enabled)->toBeTrue()
        ->and($user->emails_product_updates_enabled)->toBeFalse()
        ->and($user->emails_trial_nudges_enabled)->toBeTrue()
        ->and($user->unsubscribed_at)->not->toBeNull()
        ->and($user->unsubscribe_source)->toBe('settings_page');
});

it('/settings/notifications clears unsubscribe audit trail when master is toggled back on', function () {
    $user = User::factory()->create([
        'emails_marketing_enabled' => false,
        'unsubscribed_at' => now()->subDay(),
        'unsubscribe_source' => 'email_link',
    ]);

    Livewire::actingAs($user)
        ->test(NotificationPreferences::class)
        ->set('marketingMaster', true)
        ->call('save');

    $user->refresh();

    expect($user->emails_marketing_enabled)->toBeTrue()
        ->and($user->unsubscribed_at)->toBeNull()
        ->and($user->unsubscribe_source)->toBeNull();
});
