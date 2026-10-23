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
            // The remaining fields are useful for list surfaces (Find
            // Ranges, website range index) and null-safe when the
            // relation is not loaded. Callers that only want the
            // bare pin (EventResource venue, map pins) still ignore
            // these fields without a performance cost.
            'logo_url' => $this->logoUrl(),
            'summary' => $this->cardSummary(),
            'disciplines' => $this->whenLoaded(
                'disciplines',
                fn () => $this->disciplines
                    ->map(fn ($d) => ['slug' => $d->slug, 'name' => $d->name])
                    ->values()
                    ->all(),
                [],
            ),
            'upcoming_count' => $this->when(
                isset($this->upcoming_count),
                fn () => (int) $this->upcoming_count,
            ),
        ];
    }
}
