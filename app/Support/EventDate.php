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

    public static function weekday(CarbonInterface $starts, ?CarbonInterface $ends = null): string
    {
        if (! self::isMultiDay($starts, $ends)) {
            return $starts->timezone(self::TZ)->format('D');
        }

        return $starts->timezone(self::TZ)->format('D').'–'.$ends->timezone(self::TZ)->format('D');
    }

    public static function dayOfMonth(CarbonInterface $starts, ?CarbonInterface $ends = null): string
    {
        if (! self::isMultiDay($starts, $ends)) {
            return $starts->timezone(self::TZ)->format('j');
        }

        return $starts->timezone(self::TZ)->format('j').'–'.$ends->timezone(self::TZ)->format('j');
    }

    /**
     * Month, plus the two-digit year only when the event is not in
     * the current calendar year (JHB time).
     */
    public static function monthWithYear(CarbonInterface $starts, ?CarbonInterface $ends = null): string
    {
        $local = $starts->timezone(self::TZ);
        $currentYear = Carbon::now(self::TZ)->year;

        $startLabel = $local->year === $currentYear
            ? $local->format('M')
            : $local->format('M y');

        if (! self::isMultiDay($starts, $ends)) {
            return $startLabel;
        }

        $end = $ends->timezone(self::TZ);

        if ($local->isSameMonth($end)) {
            return $startLabel;
        }

        $endLabel = $end->year === $currentYear
            ? $end->format('M')
            : $end->format('M y');

        return $startLabel.'–'.$endLabel;
    }

    /**
     * True when the match occupies more than one calendar day in JHB time.
     * A same-day ends_at (or none) stays a single-day match.
     */
    public static function isMultiDay(CarbonInterface $starts, ?CarbonInterface $ends): bool
    {
        if ($ends === null) {
            return false;
        }

        $startDay = $starts->timezone(self::TZ)->startOfDay();
        $endDay = $ends->timezone(self::TZ)->startOfDay();

        return $endDay->greaterThan($startDay);
    }

    /**
     * Hero line. "Saturday 24 October 2026", or
     * "Saturday 24 – Sunday 25 October 2026" when it spans days.
     */
    public static function headline(CarbonInterface $starts, ?CarbonInterface $ends = null): string
    {
        $start = $starts->timezone(self::TZ);

        if (! self::isMultiDay($starts, $ends)) {
            return $start->format('l j F Y');
        }

        $end = $ends->timezone(self::TZ);

        if ($start->isSameMonth($end) && $start->year === $end->year) {
            return $start->format('l j').' – '.$end->format('l j F Y');
        }

        if ($start->year === $end->year) {
            return $start->format('l j F').' – '.$end->format('l j F Y');
        }

        return $start->format('l j F Y').' – '.$end->format('l j F Y');
    }

    /**
     * Compact fact-card line. "Sat 24 Oct 2026", or
     * "Sat 24 – Sun 25 Oct 2026".
     */
    public static function fact(CarbonInterface $starts, ?CarbonInterface $ends = null): string
    {
        $start = $starts->timezone(self::TZ);

        if (! self::isMultiDay($starts, $ends)) {
            return $start->format('D j M Y');
        }

        $end = $ends->timezone(self::TZ);

        if ($start->isSameMonth($end) && $start->year === $end->year) {
            return $start->format('D j').' – '.$end->format('D j M Y');
        }

        return $start->format('D j M').' – '.$end->format('D j M Y');
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
