<?php

namespace App\Services;

use App\Enums\Plan;
use App\Models\User;
use Illuminate\Support\Carbon;

/**
 * Starts the 30-day no-CC Pro trial for a user.
 *
 * Contract:
 *   - A user may only trial once, ever. Re-invocation on a user with
 *     pro_trial_started_at set is a no-op that returns false.
 *   - Active paid subscribers are skipped — they're already on Pro.
 *   - Cancelling subscribers keep their paid entitlement window;
 *     starting a trial on top would look like a "trial" in the UI but
 *     the paid window is longer. Not worth the complexity — skip.
 *   - Everyone else: plan set to Pro, plan_expires_at set to now + 30d
 *     (exact ceiling on midnight for a clean "your trial ends 15 Oct"
 *     UI). HasPlan::isPro() picks up the change on next request.
 *
 * The class is intentionally not injectable — it's a pure procedural
 * step called from at most three places (StartProTrial component,
 * ShooterRegister opt-in, DirectorRegister opt-in). Keeping it as a
 * static helper avoids DI ceremony for something that never varies.
 */
final class StartProTrial
{
    public const TRIAL_DAYS = 30;

    /**
     * Attempts to start a trial. Returns true if a trial was started
     * on this call, false if the user was ineligible (already trialed,
     * already Pro, currently cancelling but still Pro).
     */
    public static function for(User $user): bool
    {
        if (! self::isEligible($user)) {
            return false;
        }

        $now = Carbon::now();

        $user->forceFill([
            'plan' => Plan::Pro,
            // End of day, not exactly-now-plus-720-hours, so the "your
            // trial ends 15 Oct" copy matches what the user sees in the
            // countdown. Small kindness worth the extra minutes.
            'plan_expires_at' => $now->copy()->addDays(self::TRIAL_DAYS)->endOfDay(),
            'plan_cancelled_at' => null,
            'plan_billing_cycle' => null,
            'pro_trial_started_at' => $now,
            'pro_trial_ending_notified_at' => null,
            'pro_trial_ended_notified_at' => null,
        ])->save();

        return true;
    }

    /**
     * True if starting a trial would actually do something for this
     * user right now. Used both by StartProTrial::for and by the
     * /upgrade view to decide whether to render the trial CTA at all.
     */
    public static function isEligible(User $user): bool
    {
        // Once used, never again — regardless of whether the trial ran
        // to completion or converted mid-way.
        if ($user->hasHadTrial()) {
            return false;
        }

        // Already on Pro (comped, or in a paid window that's still
        // valid). Trial would be redundant.
        if ($user->isPro()) {
            return false;
        }

        // Cancelled but still inside their paid window — see class
        // docblock. Trial does not stack on top of a paid window.
        if ($user->isCancelling()) {
            return false;
        }

        return true;
    }
}
