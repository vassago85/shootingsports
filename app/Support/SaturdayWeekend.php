<?php

namespace App\Support;

use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;

/**
 * Saturday–Sunday window used by the answer pages.
 *
 * The calendar's "this weekend" chip still runs Friday–Sunday via
 * {@see ThisWeekend}. These pages answer the narrower question
 * "what is on this Saturday and Sunday".
 */
final class SaturdayWeekend
{
    /**
     * @return array{0: Carbon, 1: Carbon}
     */
    public static function this(?Carbon $now = null): array
    {
        $now = ($now ?? now())->copy()->timezone((string) config('app.timezone'));

        $saturday = match (true) {
            $now->isSunday() => $now->copy()->subDay()->startOfDay(),
            $now->isSaturday() => $now->copy()->startOfDay(),
            default => $now->copy()->next(Carbon::SATURDAY)->startOfDay(),
        };

        return [$saturday, $saturday->copy()->addDay()->endOfDay()];
    }

    /**
     * @return array{0: Carbon, 1: Carbon}
     */
    public static function next(?Carbon $now = null): array
    {
        [$saturday] = self::this($now);

        return self::this($saturday->copy()->addDays(2)->startOfDay());
    }

    public static function label(CarbonInterface $from, CarbonInterface $to): string
    {
        $from = $from->copy()->timezone((string) config('app.timezone'));
        $to = $to->copy()->timezone((string) config('app.timezone'));

        if ($from->isSameDay($to)) {
            return $from->format('j M Y');
        }

        if ($from->year === $to->year && $from->month === $to->month) {
            return $from->format('j').'–'.$to->format('j M Y');
        }

        if ($from->year === $to->year) {
            return $from->format('j M').'–'.$to->format('j M Y');
        }

        return $from->format('j M Y').'–'.$to->format('j M Y');
    }
}
