<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\EventStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\EventDetailResource;
use App\Http\Resources\Api\V1\EventResource;
use App\Models\Event;
use App\Queries\PublicEventQuery;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * Read-only discovery endpoints for matches. Filters mirror the
 * request contract exposed by PublicEventQuery::fromRequest() so a
 * mobile client can pass the same query string the web calendar uses.
 */
class EventController extends Controller
{
    /**
     * List upcoming matches, filtered.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        // Cap client-supplied limit so a bad `?limit=999999` cannot
        // spin the DB. `null` -> default page size handled by the
        // limit clause; PublicEventQuery has no built-in pagination
        // so we return a flat collection with a hard ceiling.
        $limit = (int) $request->integer('limit');
        $limit = $limit > 0 ? min($limit, 200) : 100;

        $query = PublicEventQuery::fromRequest($request);
        $query->limit = $limit;

        $events = $query->get();

        return EventResource::collection($events)->additional([
            'meta' => [
                'count' => $events->count(),
                'unpinned_skipped' => $query->unpinnedSkipped,
            ],
        ]);
    }

    public function show(Event $event): EventDetailResource
    {
        // Same visibility rule as the web EventController: drafts stay
        // hidden from the API too.
        abort_if($event->status === EventStatus::Draft, 404);

        $event->load(['hostOrganisation.parent', 'venue', 'venues', 'disciplines', 'flags', 'banner']);

        return EventDetailResource::make($event);
    }
}
