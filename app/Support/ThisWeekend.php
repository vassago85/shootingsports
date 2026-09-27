<?php

namespace App\Support;

use Illuminate\Support\Carbon;

/**
 * Resolves the Friday–Sunday window for "This Weekend" discovery
 * shortcuts. Timezone follows the app clock (Africa/Johannesburg).
 *
 * Rules:
 * - Mon–Thu → upcoming Friday 00:00 through Sunday 23:59:59
 * - Friday → today through Sunday
 * - Saturday → today through Sunday (Friday has already passed)
 * - Sunday → today only (still "this weekend")
 */
class ThisWeekend
{
    /**
     * @return array{0: Carbon, 1: Carbon}
     */
    public static function range(?Carbon $now = null): array
    {
        $now = ($now ?? now())->copy()->timezone(config('app.timezone'));

        if ($now->isSunday()) {
            return [$now->copy()->startOfDay(), $now->copy()->endOfDay()];
        }

        $start = ($now->isFriday() || $now->isSaturday())
            ? $now->copy()->startOfDay()
            : $now->copy()->next(Carbon::FRIDAY)->startOfDay();

        $sunday = $start->copy()->next(Carbon::SUNDAY)->endOfDay();

        return [$start, $sunday];
    }

    /**
     * The full Friday–Sunday after the current weekend window. On Sunday
     * the current window is today only, so this must not be "today + 7 days"
     * or next weekend collapses to the following Sunday and drops Saturday.
     *
     * @return array{0: Carbon, 1: Carbon}
     */
    public static function nextRange(?Carbon $now = null): array
    {
        [, $weekendEnd] = self::range($now);

        return self::range($weekendEnd->copy()->addDay()->startOfDay());
    }

    public static function from(?Carbon $now = null): Carbon
    {
        return self::range($now)[0];
    }

    public static function to(?Carbon $now = null): Carbon
    {
        return self::range($now)[1];
    }

    /**
     * Short label for chips and empty states, e.g. "18–20 Sep".
     */
    public static function label(?Carbon $now = null): string
    {
        [$from, $to] = self::range($now);

        return self::labelFor($from, $to);
    }

    public static function nextLabel(?Carbon $now = null): string
    {
        [$from, $to] = self::nextRange($now);

        return self::labelFor($from, $to);
    }

    public static function labelFor(Carbon $from, Carbon $to): string
    {
        if ($from->isSameDay($to)) {
            return $from->format('j M');
        }

        if ($from->month === $to->month) {
            return $from->format('j').'–'.$to->format('j M');
        }

        return $from->format('j M').'–'.$to->format('j M');
    }
}
