<?php

namespace App\Support;

use App\Models\Event;
use Illuminate\Support\Collection;

/**
 * Upcoming matches grouped by the ranges that actually host them.
 *
 * Reads both the legacy venue_id and the event_venue pivot, so a
 * match stored only on the pivot still counts, and a match with no
 * range is reported separately instead of disappearing into the total.
 */
class UpcomingVenueCounts
{
    /**
     * @return array{byVenue: Collection<int, int>, linked: int, unlinked: int}
     */
    public static function tally(): array
    {
        $events = Event::query()
            ->upcoming()
            ->with('venues')
            ->get(['id', 'venue_id']);

        /** @var array<int, int> $byVenue */
        $byVenue = [];
        $linked = 0;

        foreach ($events as $event) {
            $ids = $event->venues->pluck('id');

            if ($event->venue_id !== null) {
                $ids->push($event->venue_id);
            }

            $ids = $ids->filter()->unique()->values();

            if ($ids->isEmpty()) {
                continue;
            }

            $linked++;

            foreach ($ids as $id) {
                $venueId = (int) $id;
                $byVenue[$venueId] = ($byVenue[$venueId] ?? 0) + 1;
            }
        }

        return [
            'byVenue' => collect($byVenue),
            'linked' => $linked,
            'unlinked' => $events->count() - $linked,
        ];
    }
}
