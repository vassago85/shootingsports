<?php

namespace App\Http\Resources\Api\V1;

use App\Models\Venue;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Venue
 */
class VenueResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'slug' => $this->slug,
            'name' => $this->name,
            'town' => $this->town,
            'province' => $this->province?->getLabel(),
            'province_slug' => $this->province?->urlSlug(),
            'lat' => $this->lat !== null ? (float) $this->lat : null,
            'lng' => $this->lng !== null ? (float) $this->lng : null,
            'url' => route('ranges.show', $this->slug),
        ];
    }
}
