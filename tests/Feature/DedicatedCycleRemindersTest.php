<?php

use App\Enums\Plan;
use App\Mail\DedicatedCycleReminder;
use App\Models\Flag;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Mail;

beforeEach(function (): void {
    config()->set('coming-soon.enabled', false);
    Mail::fake();

    Flag::query()->firstOrCreate(
        ['slug' => 'counts-toward-activity-requirements'],
        [
            'name' => 'Counts toward activity requirements',
            'family' => 'competition',
            'definition' => 'Attendance can be used toward dedicated-activity requirements.',
            'sort_order' => 100,
        ]
    );
});

it('sends a reminder to a Pro user with an incomplete cycle on a trigger day', function () {
    // 24 October 2026 is 7 days before the 31 October cycle end — one
    // of the configured reminder days.
    Carbon::setTestNow('2026-10-24');

    $user = User::factory()->create([
        'plan' => Plan::Pro,
        'plan_expires_at' => now()->addYear(),
        'dedicated_body' => 'nrapa',
        'dedicated_required_count' => 2,
    ]);

    Artisan::call('dedicated:send-reminders');

    Mail::assertQueued(DedicatedCycleReminder::class, fn ($mail) => $mail->user->is($user));

    expect($user->refresh()->dedicated_last_reminded_cycle_end?->toDateString())
        ->toBe('2026-10-31');

    Carbon::setTestNow();
});

it('does not re-send once the user has already been reminded this cycle', function () {
    Carbon::setTestNow('2026-10-24');

    $user = User::factory()->create([
        'plan' => Plan::Pro,
        'plan_expires_at' => now()->addYear(),
        'dedicated_body' => 'nrapa',
        'dedicated_required_count' => 2,
        'dedicated_last_reminded_cycle_end' => '2026-10-31',
    ]);

    Artisan::call('dedicated:send-reminders');

    Mail::assertNotQueued(DedicatedCycleReminder::class);

    Carbon::setTestNow();
});

it('skips users who have the cycle-reminder preference turned off', function () {
    Carbon::setTestNow('2026-10-24');

    $user = User::factory()->create([
        'plan' => Plan::Pro,
        'plan_expires_at' => now()->addYear(),
        'dedicated_body' => 'nrapa',
        'dedicated_required_count' => 2,
        'emails_dedicated_reminders_enabled' => false,
    ]);

    Artisan::call('dedicated:send-reminders');

    Mail::assertNotQueued(DedicatedCycleReminder::class);

    Carbon::setTestNow();
});

it('skips Free users even if they have the body set', function () {
    Carbon::setTestNow('2026-10-24');

    User::factory()->create([
        'dedicated_body' => 'nrapa',
        'dedicated_required_count' => 2,
    ]);

    Artisan::call('dedicated:send-reminders');

    Mail::assertNotQueued(DedicatedCycleReminder::class);

    Carbon::setTestNow();
});

it('does nothing on a day that is not a configured reminder trigger', function () {
    // 20 October 2026 is 11 days before the cycle end — not in the
    // default [60, 30, 7] list.
    Carbon::setTestNow('2026-10-20');

    User::factory()->create([
        'plan' => Plan::Pro,
        'plan_expires_at' => now()->addYear(),
        'dedicated_body' => 'nrapa',
        'dedicated_required_count' => 2,
    ]);

    Artisan::call('dedicated:send-reminders');

    Mail::assertNotQueued(DedicatedCycleReminder::class);

    Carbon::setTestNow();
});

it('--force sends regardless of the day', function () {
    Carbon::setTestNow('2026-02-01'); // No reminder day near here.

    $user = User::factory()->create([
        'plan' => Plan::Pro,
        'plan_expires_at' => now()->addYear(),
        'dedicated_body' => 'nrapa',
        'dedicated_required_count' => 2,
    ]);

    Artisan::call('dedicated:send-reminders', ['--force' => true]);

    Mail::assertQueued(DedicatedCycleReminder::class, fn ($mail) => $mail->user->is($user));

    Carbon::setTestNow();
});
