<?php

namespace App\Http\Resources\Api\V1;

use App\Models\Event;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Card-shape Event resource for list endpoints. The mobile calendar
 * screen renders every field on this shape; heavier detail (spec
 * rows, description, host contact) belongs on EventDetailResource.
 *
 * @mixin Event
 */
class EventResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'slug' => $this->slug,
            'title' => $this->title,
            'starts_at' => $this->starts_at?->toIso8601String(),
            'ends_at' => $this->ends_at?->toIso8601String(),
            'all_day' => (bool) $this->all_day,
            'level' => $this->level?->value,
            'status' => $this->status?->value,
            'confirmed_at' => $this->confirmed_at?->toIso8601String(),
            'entry_url' => $this->entry_url,
            'entry_fee_cents' => $this->entry_fee_cents,
            'member_fee_cents' => $this->member_fee_cents,
            'capacity' => $this->capacity,
            'entries_taken' => $this->entries_taken,
            'novice_friendly' => $this->relationLoaded('flags')
                ? $this->flags->contains('slug', 'new-shooter-friendly')
                : null,
            'host' => [
                'name' => $this->hostDisplayName(),
                'organisation' => OrganisationResource::make($this->whenLoaded('hostOrganisation')),
            ],
            'venue' => VenueResource::make($this->whenLoaded('venue')),
            'location_label' => $this->locationLabel(),
            'disciplines' => DisciplineResource::collection($this->whenLoaded('disciplines')),
            'banner_url' => $this->coverImageUrl(),
            'url' => $this->publicUrl(),
            // Null for events that have never been materially edited.
            // The mobile client uses this + the save-pivot timestamp
            // to decide whether to show a "Something changed" badge.
            'last_material_change_at' => $this->last_material_change_at?->toIso8601String(),
            // Only emitted when this resource was reached via the
            // saved-events pivot (either /me/saved-events or the
            // /me/feed `saved` bucket). True when the material-change
            // clock has ticked since the shooter hit Save.
            'changed' => $this->whenPivotLoaded(
                'saved_events',
                fn (): bool => $this->last_material_change_at !== null
                    && $this->pivot->created_at !== null
                    && $this->last_material_change_at->greaterThan($this->pivot->created_at),
            ),
        ];
    }
}
