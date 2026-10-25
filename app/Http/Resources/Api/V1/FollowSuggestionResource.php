<?php

namespace App\Http\Resources\Api\V1;

use App\Models\Organisation;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Wraps OrganisationResource with the per-request `upcoming_count`
 * computed by the follow-suggestion ranker. The base organisation
 * payload stays canonical (same shape as /organisations/{slug});
 * the count rides alongside so the mobile row can show
 * "3 upcoming matches" without a second lookup.
 */
class FollowSuggestionResource extends JsonResource
{
    public function __construct(Organisation $organisation, private int $upcomingCount)
    {
        parent::__construct($organisation);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return array_merge(
            OrganisationResource::make($this->resource)->toArray($request),
            ['upcoming_count' => $this->upcomingCount],
        );
    }
}
