<?php

namespace App\Http\Resources\Api\V1;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Public profile shape returned to the mobile client. Deliberately
 * excludes admin/MD flags, subscription internals, and unsubscribe
 * tokens — the client only needs identity + display + plan.
 *
 * @mixin User
 */
class UserResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'calendar_slug' => $this->calendar_slug,
            'home_province' => $this->home_province?->getLabel(),
            'home_province_slug' => $this->home_province?->urlSlug(),
            'travel_radius_km' => $this->travel_radius_km,
            'plan' => $this->plan?->value,
            'is_pro' => $this->isPro(),
            'on_trial' => $this->isOnTrial(),
            'email_prefs' => [
                'marketing' => (bool) $this->emails_marketing_enabled,
                'match_alerts' => (bool) $this->emails_match_alerts_enabled,
                'weekly_digest' => (bool) $this->emails_weekly_digest_enabled,
                'product_updates' => (bool) $this->emails_product_updates_enabled,
            ],
        ];
    }
}
