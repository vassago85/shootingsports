<?php

namespace App\Support;

use App\Models\Event;
use Illuminate\Database\Eloquent\Builder;

/**
 * One or two sentences of live calendar facts for a meta description.
 * Always starts with a capital letter.
 */
final class UpcomingCopy
{
    /**
     * @param  Builder<Event>  $query
     */
    public static function facts(Builder $query): string
    {
        $count = (clone $query)->count();

        if ($count === 0) {
            return 'No upcoming match is listed yet.';
        }

        $next = (clone $query)->reorder()->orderBy('starts_at')->first();
        $when = $next?->starts_at?->timezone((string) config('app.timezone'))->format('j M Y');
        $listed = $count === 1
            ? 'There is 1 upcoming match.'
            : 'There are '.$count.' upcoming matches.';

        if ($next === null || $when === null) {
            return $listed;
        }

        return $listed.' The next is '.$next->title.' on '.$when.'.';
    }
}
