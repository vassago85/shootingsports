<?php

namespace App\Http\Resources\Api\V1;

use App\Models\Organisation;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Organisation
 */
class OrganisationResource extends JsonResource
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
            'short_name' => $this->short_name,
            'type' => $this->type?->value,
            'province' => $this->province?->getLabel(),
            'province_slug' => $this->province?->urlSlug(),
            'town' => $this->town,
            'logo_url' => $this->logoUrl(),
            'url' => route('clubs.show', $this->slug),
        ];
    }
}
