<?php

namespace App\Http\Resources\Api\V1;

use App\Models\Event;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * One row in a /me/feed bucket (this_weekend, next_30_days). Wraps
 * the standard EventResource and appends the per-user `reasons`
 * array that answers "why am I seeing this match?".
 *
 * The reasons list is pre-sorted (clubs first, disciplines second)
 * and pre-deduplicated — the mobile client renders it as-is.
 */
class FeedEntryResource extends JsonResource
{
    /**
     * @param  array<int, array{type: string, name: string, province?: ?string}>  $reasons
     */
    public function __construct(Event $event, private array $reasons)
    {
        parent::__construct($event);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return array_merge(
            EventResource::make($this->resource)->toArray($request),
            ['reasons' => $this->reasons],
        );
    }
}
