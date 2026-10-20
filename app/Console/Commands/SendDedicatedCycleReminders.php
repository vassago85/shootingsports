<?php

namespace App\Console\Commands;

use App\Enums\EmailCategory;
use App\Mail\DedicatedCycleReminder;
use App\Models\User;
use App\Support\EmailPreferences;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Mail;

/**
 * Dispatch dedicated-status cycle reminders.
 *
 * Runs daily. For each configured "days before cycle end" entry
 * (default 60, 30 and 7 — i.e. 1 September, 1 October and
 * 24 October when the cycle ends 31 October), finds every Pro user
 * who still has activities to log and queues a reminder mail.
 *
 * Idempotent: the user's `dedicated_last_reminded_cycle_end` is set to
 * the cycle end date on first send, so re-running on the same day
 * (or any day up to cycle end) does not re-mail.
 *
 * Honours the user's email preferences via
 * {@see EmailPreferences::canSend()} — users who have the
 * DedicatedReminders category off get silently skipped.
 */
class SendDedicatedCycleReminders extends Command
{
    protected $signature = 'dedicated:send-reminders {--force : Ignore the days-before window and send to every eligible user}';

    protected $description = 'Email Pro users who still have dedicated-status activities to log this cycle.';

    public function handle(): int
    {
        $today = Carbon::today();
        $daysBefore = (array) config('dedicated.reminder_days_before', [60, 30, 7]);
        $force = (bool) $this->option('force');

        $bodies = (array) config('dedicated.bodies', []);
        $validCodes = array_keys($bodies);

        if ($validCodes === []) {
            $this->warn('No accrediting bodies configured — nothing to do.');

            return self::SUCCESS;
        }

        $sent = 0;
        $skippedPrefs = 0;
        $skippedComplete = 0;
        $skippedAlready = 0;

        User::query()
            ->whereIn('dedicated_body', $validCodes)
            ->whereNotNull('dedicated_body')
            ->chunkById(200, function ($users) use (&$sent, &$skippedPrefs, &$skippedComplete, &$skippedAlready, $daysBefore, $force): void {
                /** @var Collection<int, User> $users */
                foreach ($users as $user) {
                    // Pro gate: only Pro users get the widget, so only
                    // Pro users should get the reminder.
                    if (! $user->hasDedicatedTracker()) {
                        continue;
                    }

                    $progress = $user->dedicatedProgress();
                    $daysLeft = (int) $progress['days_to_renewal'];

                    // Window check — skip entirely unless today lines
                    // up with one of the configured "days before"
                    // triggers, OR --force is set.
                    if (! $force && ! in_array($daysLeft, $daysBefore, true)) {
                        continue;
                    }

                    // Nothing to remind about — they're already done.
                    if ($progress['remaining'] <= 0) {
                        $skippedComplete++;

                        continue;
                    }

                    // Idempotency: one mail per cycle end. Flipping
                    // the record forward prevents a re-send if the
                    // schedule fires twice or the command is manually
                    // re-run within the same cycle.
                    $cycleEnd = $progress['window_end']->copy()->startOfDay();
                    if (
                        $user->dedicated_last_reminded_cycle_end !== null
                        && $user->dedicated_last_reminded_cycle_end->copy()->startOfDay()->equalTo($cycleEnd)
                    ) {
                        $skippedAlready++;

                        continue;
                    }

                    if (! EmailPreferences::canSend($user, EmailCategory::DedicatedReminders)) {
                        $skippedPrefs++;

                        continue;
                    }

                    Mail::to($user)->queue(new DedicatedCycleReminder($user, $progress));

                    $user->forceFill(['dedicated_last_reminded_cycle_end' => $cycleEnd])->save();
                    $sent++;
                }
            });

        $this->info(sprintf(
            'Sent %d reminders. Skipped %d (prefs off), %d (cycle complete), %d (already notified this cycle).',
            $sent,
            $skippedPrefs,
            $skippedComplete,
            $skippedAlready,
        ));

        return self::SUCCESS;
    }
}
