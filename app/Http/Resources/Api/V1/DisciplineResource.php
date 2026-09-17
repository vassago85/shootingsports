<?php

namespace App\Http\Resources\Api\V1;

use App\Models\Discipline;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Discipline
 */
class DisciplineResource extends JsonResource
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
            'family' => $this->family?->value,
            'family_label' => $this->family?->getLabel(),
            'short_blurb' => $this->short_blurb,
            'parent_id' => $this->parent_id,
            'upcoming_count' => $this->whenNotNull($this->events_count ?? null),
            'url' => route('disciplines.show', $this->slug),
        ];
    }
}
