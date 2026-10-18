<?php

namespace App\Console\Commands;

use App\Enums\OrganisationUserRole;
use App\Enums\PlatformSubscriptionStatus;
use App\Mail\TrialEndingReminder;
use App\Models\PlatformSubscription;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;

/**
 * Nightly sweep: mails organisation admins and secretaries when a
 * platform trial ends in 5 days, tomorrow, or today. Matches on the
 * calendar date of `trial_ends_at`, so running once a day hits each
 * subscription at each offset exactly once.
 */
class NotifyTrialEnding extends Command
{
    /** @var list<int> */
    private const REMINDER_OFFSETS_IN_DAYS = [5, 1, 0];

    protected $signature = 'subscriptions:notify-trial-ending';

    protected $description = 'Mail trial-ending reminders at T-5, T-1 and T+0 to organisation admins and secretaries.';

    public function handle(): int
    {
        $sent = 0;

        foreach (self::REMINDER_OFFSETS_IN_DAYS as $offset) {
            $subscriptions = PlatformSubscription::query()
                ->with('organisation')
                ->where('status', PlatformSubscriptionStatus::Trial)
                ->whereDate('trial_ends_at', today()->addDays($offset))
                ->get();

            foreach ($subscriptions as $subscription) {
                $recipients = $subscription->organisation->managers()
                    ->wherePivotIn('role', [
                        OrganisationUserRole::Admin->value,
                        OrganisationUserRole::Secretary->value,
                    ])
                    ->get();

                foreach ($recipients as $recipient) {
                    Mail::to($recipient)->send(new TrialEndingReminder(
                        $subscription,
                        $offset,
                        url('/desk/billing'),
                    ));
                    $sent++;
                }
            }
        }

        $this->info("{$sent} trial-ending reminder(s) sent.");

        return self::SUCCESS;
    }
}
