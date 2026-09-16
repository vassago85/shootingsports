<?php

namespace App\Console\Commands;

use App\Enums\EmailCategory;
use App\Mail\ProTrialEnded;
use App\Mail\ProTrialEndingSoon;
use App\Models\User;
use App\Support\EmailPreferences;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;

/**
 * Daily task that sends the two Pro-trial nudge emails.
 *
 * Ending-soon (T-3):
 *   Users whose plan_expires_at is between now+2 days and now+4 days
 *   (2-day window to catch DST / clock-skew), who are still on the
 *   trial, who haven't been nudged yet, and who haven't opted out
 *   of trial-nudge emails. Stamps pro_trial_ending_notified_at.
 *
 * Ended (T+0):
 *   Users whose plan_expires_at is in the past (trial window is
 *   already closed — HasPlan::isPro naturally returns false),
 *   pro_trial_started_at is set (so this was a trial, not a paid
 *   subscription that lapsed), and pro_trial_ended_notified_at is
 *   null. Stamps pro_trial_ended_notified_at.
 *
 * Both queries are set-based and idempotent — running the command
 * twice on the same day is safe. No queue is required; the mailables
 * are ShouldQueue so they get dispatched to whatever queue driver is
 * configured.
 *
 * Wired up in routes/console.php as a daily 07:00 SAST task.
 */
class SendProTrialNudges extends Command
{
    protected $signature = 'pro:trial-nudges {--dry : List candidates without sending}';

    protected $description = 'Send the T-3 "trial ending soon" and T+0 "trial ended" emails to Pro-trial users. Idempotent.';

    public function handle(): int
    {
        $dry = (bool) $this->option('dry');
        $now = Carbon::now();

        $endingSoonSent = $this->handleEndingSoon($now, $dry);
        $endedSent = $this->handleEnded($now, $dry);

        $this->info(sprintf(
            '%s ending-soon nudges, %s ended nudges%s.',
            $endingSoonSent,
            $endedSent,
            $dry ? ' (dry run — nothing sent)' : ''
        ));

        return self::SUCCESS;
    }

    private function handleEndingSoon(Carbon $now, bool $dry): int
    {
        // Two-day window centred on T-3 so an off-by-a-few-hours (DST,
        // scheduler drift, queue backlog) still catches everyone.
        $windowStart = $now->copy()->addDays(2)->startOfDay();
        $windowEnd = $now->copy()->addDays(4)->endOfDay();

        $candidates = User::query()
            ->whereNotNull('pro_trial_started_at')
            ->whereNull('paystack_subscription_code')
            ->whereNull('pro_trial_ending_notified_at')
            ->whereBetween('plan_expires_at', [$windowStart, $windowEnd])
            ->cursor();

        $sent = 0;

        foreach ($candidates as $user) {
            if (! $user->isOnTrial()) {
                continue;
            }

            if (! EmailPreferences::canSend($user, EmailCategory::TrialNudges)) {
                // Still stamp so we don't recheck this user tomorrow —
                // if they resubscribe to nudges after T-3 they can
                // enjoy silence (this window has passed anyway).
                if (! $dry) {
                    $user->forceFill(['pro_trial_ending_notified_at' => $now])->save();
                }

                continue;
            }

            if (! $dry) {
                Mail::send(new ProTrialEndingSoon($user));
                $user->forceFill(['pro_trial_ending_notified_at' => $now])->save();
            }

            $sent++;
        }

        return $sent;
    }

    private function handleEnded(Carbon $now, bool $dry): int
    {
        $candidates = User::query()
            ->whereNotNull('pro_trial_started_at')
            ->whereNull('paystack_subscription_code')
            ->whereNull('pro_trial_ended_notified_at')
            ->where('plan_expires_at', '<', $now)
            ->cursor();

        $sent = 0;

        foreach ($candidates as $user) {
            // Skip anyone who has taken a paid subscription in the
            // interim (belt-and-braces — the query already filters
            // on null paystack_subscription_code, but a user could
            // theoretically have paid and the code is still writing).
            if ($user->hasActiveSubscription()) {
                continue;
            }

            if (! EmailPreferences::canSend($user, EmailCategory::TrialNudges)) {
                if (! $dry) {
                    $user->forceFill(['pro_trial_ended_notified_at' => $now])->save();
                }

                continue;
            }

            if (! $dry) {
                Mail::send(new ProTrialEnded($user));
                $user->forceFill(['pro_trial_ended_notified_at' => $now])->save();
            }

            $sent++;
        }

        return $sent;
    }
}
