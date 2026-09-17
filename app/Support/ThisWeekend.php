<?php

namespace App\Support;

use Illuminate\Support\Carbon;

/**
 * Resolves the upcoming Saturday–Sunday window for "This Weekend"
 * discovery shortcuts. Timezone follows the app clock (Africa/Johannesburg).
 *
 * Rules:
 * - Mon–Fri → next Saturday 00:00 through Sunday 23:59:59
 * - Saturday → today through Sunday
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

        $saturday = $now->isSaturday()
            ? $now->copy()->startOfDay()
            : $now->copy()->next(Carbon::SATURDAY)->startOfDay();

        $sunday = $saturday->copy()->addDay()->endOfDay();

        return [$saturday, $sunday];
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
     * Short label for chips and empty states, e.g. "19–20 Sep".
     */
    public static function label(?Carbon $now = null): string
    {
        [$from, $to] = self::range($now);

        if ($from->isSameDay($to)) {
            return $from->format('j M');
        }

        if ($from->month === $to->month) {
            return $from->format('j').'–'.$to->format('j M');
        }

        return $from->format('j M').'–'.$to->format('j M');
    }
}
