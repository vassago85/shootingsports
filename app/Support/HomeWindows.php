<?php

namespace App\Support;

use Illuminate\Support\Carbon;

/**
 * Date windows shared by every "what's on" surface — the public web
 * home rail, the mobile /me/feed buckets, and anything else that
 * needs to answer "this weekend?" or "within the next 30 days?".
 *
 * All times are resolved in the app timezone (Africa/Johannesburg)
 * so clients and server agree on which day a match belongs to.
 *
 * The weekend window delegates to App\Support\ThisWeekend. The 30-day
 * window is just [now, now + 30 days] — it lives here so there is a
 * single source of truth both the HomeController blade view and the
 * mobile MeFeedController reach for. If the window ever changes
 * ("next 14 days" / "next 6 weekends"), one edit updates everyone.
 */
class HomeWindows
{
    /**
     * Friday–Sunday window (or the appropriate variant for the current
     * weekday). See App\Support\ThisWeekend for the full rules.
     *
     * @return array{0: Carbon, 1: Carbon}
     */
    public static function weekend(?Carbon $now = null): array
    {
        return ThisWeekend::range($now);
    }

    /**
     * Rolling 30-day window starting from the given moment (defaults
     * to now). Inclusive on both ends; callers typically combine this
     * with Event::upcoming() to drop anything already past.
     *
     * @return array{0: Carbon, 1: Carbon}
     */
    public static function next30Days(?Carbon $now = null): array
    {
        $start = ($now ?? now())->copy()->timezone(config('app.timezone'));

        return [$start, $start->copy()->addDays(30)];
    }
}
