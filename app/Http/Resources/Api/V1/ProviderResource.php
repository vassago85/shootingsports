<?php

namespace App\Http\Resources\Api\V1;

use App\Models\Provider;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Provider
 */
class ProviderResource extends JsonResource
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
            'category' => $this->category?->value,
            'category_label' => $this->category?->getLabel(),
            'town' => $this->town,
            'province' => $this->province?->getLabel(),
            'province_slug' => $this->province?->urlSlug(),
            'tagline' => $this->tagline,
            'summary' => $this->cardSummary(),
            'logo_url' => $this->logoUrl(),
            'tier' => $this->tier?->value,
            'url' => route('suppliers.show', $this->slug),
        ];
    }
}
