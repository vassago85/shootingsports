<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\Province;
use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\VenueResource;
use App\Models\Event;
use App\Models\Venue;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * Public venues ("ranges") directory for the mobile Find screen.
 *
 * Scopes to `published()` only — the desk draft/claim workflow lives
 * on the web Filament panel and must never leak through here.
 */
class VenueController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $province = $request->string('province')->toString() !== ''
            ? Province::fromUrlSlug($request->string('province')->toString())
                ?? Province::tryFrom($request->string('province')->toString())
            : null;

        $search = trim($request->string('q')->toString());

        $upcoming = $this->upcomingCountsByVenue();

        $venues = Venue::query()
            ->published()
            ->with('disciplines.parent')
            ->when($province, fn ($q) => $q->where('province', $province))
            ->when($search !== '', function ($q) use ($search): void {
                $like = '%'.addcslashes($search, '%_\\').'%';
                $q->where(function ($inner) use ($like): void {
                    $inner->where('name', 'like', $like)
                        ->orWhere('town', 'like', $like);
                });
            })
            ->orderByTier()
            ->get()
            ->each(function (Venue $venue) use ($upcoming): void {
                $venue->setAttribute('upcoming_count', $upcoming[$venue->id] ?? 0);
            });

        return VenueResource::collection($venues);
    }

    /**
     * One query that returns upcoming event counts keyed by venue id.
     * Dual-reads the legacy `venue_id` and the `event_venue` pivot so
     * multi-venue matches are counted against every range on their
     * weekend — matching the Public API's `?venue=` filter semantics.
     *
     * @return array<int, int>
     */
    private function upcomingCountsByVenue(): array
    {
        $legacy = Event::query()
            ->upcoming()
            ->whereNotNull('venue_id')
            ->selectRaw('venue_id, count(*) as c')
            ->groupBy('venue_id')
            ->pluck('c', 'venue_id');

        $pivot = Event::query()
            ->upcoming()
            ->join('event_venue', 'event_venue.event_id', '=', 'events.id')
            ->selectRaw('event_venue.venue_id, count(*) as c')
            ->groupBy('event_venue.venue_id')
            ->pluck('c', 'event_venue.venue_id');

        $out = [];

        foreach ($legacy as $id => $count) {
            $out[(int) $id] = (int) $count;
        }

        foreach ($pivot as $id => $count) {
            $out[(int) $id] = ($out[(int) $id] ?? 0) + (int) $count;
        }

        return $out;
    }
}
