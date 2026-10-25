<?php

namespace App\Http\Resources\Api\V1;

use App\Models\Discipline;
use App\Models\Follow;
use App\Models\Organisation;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Minimal shape for the /me/follows list. Enough to render a row
 * (name, slug, icon colour, "Unfollow" button) without a second
 * roundtrip per follow. Full discipline / organisation payloads
 * stay on their own detail endpoints.
 *
 * The outbound `type` is "club" for organisations (matching the
 * mobile app's domain language) even though the DB morph alias is
 * "organisation". POST /me/follows accepts both on input so a
 * round-trip of what we return here Just Works.
 *
 * @mixin Follow
 */
class FollowResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $target = $this->whenLoaded('followable', $this->followable);

        return [
            'id' => $this->id,
            'type' => $this->publicType(),
            'followable_id' => $this->followable_id,
            'name' => $target instanceof Discipline
                ? $target->displayName() // collapses child → parent label
                : $target?->name,
            'slug' => $target?->slug,
            'group' => $target instanceof Organisation
                ? ($target->isNationalBody() ? 'national' : 'regional')
                : null,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }

    private function publicType(): string
    {
        return match ($this->followable_type) {
            'organisation' => 'club',
            'discipline' => 'discipline',
            default => $this->followable_type,
        };
    }
}
