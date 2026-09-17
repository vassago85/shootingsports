<?php

namespace App\Http\Resources\Api\V1;

use App\Models\Event;
use Illuminate\Http\Request;

/**
 * Adds description, results URL, all venues, and round/target/stage
 * counts to the base card shape. Used by `GET /api/v1/events/{slug}`.
 *
 * @mixin Event
 */
class EventDetailResource extends EventResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return array_merge(parent::toArray($request), [
            'description' => $this->description,
            'results_url' => $this->results_url,
            'round_count' => $this->round_count,
            'target_count' => $this->target_count,
            'stage_count' => $this->stage_count,
            'venues' => VenueResource::collection($this->allVenues()),
        ]);
    }
}
