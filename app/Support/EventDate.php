<?php

namespace App\Support;

use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;

/**
 * Shared date-formatting helpers for the public match cards.
 *
 * Shooters plan around weekends, so every card shows the weekday.
 * The current year is dropped from the small line so "SEP" is not
 * fighting "SEP 26" for pixels — the moment the date rolls into a
 * different calendar year the year snaps back in as "SEP 27".
 *
 * Accepts CarbonInterface because Event's starts_at cast returns
 * CarbonImmutable but the tests / factories hand in mutable
 * Illuminate\Support\Carbon. Both are fine — we only read.
 */
class EventDate
{
    private const TZ = 'Africa/Johannesburg';

    public static function weekday(CarbonInterface $starts): string
    {
        return $starts->timezone(self::TZ)->format('D');
    }

    public static function dayOfMonth(CarbonInterface $starts): string
    {
        return $starts->timezone(self::TZ)->format('j');
    }

    /**
     * Month, plus the two-digit year only when the event is not in
     * the current calendar year (JHB time).
     */
    public static function monthWithYear(CarbonInterface $starts): string
    {
        $local = $starts->timezone(self::TZ);
        $currentYear = Carbon::now(self::TZ)->year;

        return $local->year === $currentYear
            ? $local->format('M')
            : $local->format('M y');
    }

    /**
     * "Sat 19 Sep" or "Sat 19 Sep 27" when the date has rolled over.
     */
    public static function short(CarbonInterface $starts): string
    {
        return sprintf(
            '%s %s %s',
            self::weekday($starts),
            self::dayOfMonth($starts),
            self::monthWithYear($starts),
        );
    }
}
