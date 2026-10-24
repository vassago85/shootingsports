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
            // "national" for federations and province-less
            // associations (NRL Hunter SA, SAHRSA). "regional" for
            // everything else — clubs, series, provincial bodies
            // and province-bound associations. The mobile Clubs tab
            // uses this to render a National group at the top so
            // bodies that cover the whole country are reachable
            // without scrolling through 9 provinces first.
            'group' => $this->isNationalBody() ? 'national' : 'regional',
            // Nullable boolean: null means "not asked yet", true
            // means explicitly welcoming, false means explicitly
            // not. The mobile app only renders the Visitors badge
            // when this is literally true.
            'visitors_welcome' => $this->visitors_welcome === null
                ? null
                : (bool) $this->visitors_welcome,
            'disciplines' => $this->whenLoaded(
                'disciplines',
                fn () => $this->disciplines
                    ->map(fn ($d) => ['slug' => $d->slug, 'name' => $d->name])
                    ->values()
                    ->all(),
                [],
            ),
        ];
    }
}
