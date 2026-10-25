<?php

namespace App\Http\Resources\Api\V1;

use App\Enums\Province;
use App\Models\User;
use App\Support\DeskCapabilities;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Public profile shape returned to the mobile client. Deliberately
 * excludes admin/MD flags, subscription internals, and unsubscribe
 * tokens — the client only needs identity + display + plan +
 * capability flags so it can decide which tabs and cards to render.
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
        $capabilities = DeskCapabilities::kindsFor($this->resource);

        $followCount = $this->follows()->count();
        $savedSearchCount = $this->savedSearches()->count();
        $logCount = $this->attendedEvents()->count();

        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'calendar_slug' => $this->calendar_slug,
            'home_province' => $this->home_province?->getLabel(),
            'home_province_slug' => $this->home_province?->urlSlug(),
            // Secondary provinces for the /me/feed discipline-follow
            // scoping. Returned as [{label, slug}] mirroring the
            // single home_province representation above so the mobile
            // client doesn't need to re-enum anything client-side.
            'extra_provinces' => collect($this->extra_provinces ?? [])
                ->map(fn (string $value) => Province::tryFrom($value))
                ->filter()
                ->map(fn (Province $province) => [
                    'label' => $province->getLabel(),
                    'slug' => $province->urlSlug(),
                ])
                ->values()
                ->all(),
            'travel_radius_km' => $this->travel_radius_km,
            'plan' => $this->plan?->value,
            'is_pro' => $this->isPro(),
            'on_trial' => $this->isOnTrial(),
            'trial_days_remaining' => $this->trialDaysRemaining(),

            // Capability summary — just the kinds so the mobile client
            // can decide whether to render the Manage hub without
            // waterfall-fetching the detailed card payload. Call
            // GET /api/v1/me/capabilities for names + URLs.
            'capabilities' => $capabilities,
            'has_desk_access' => $this->hasDeskAccess(),

            // Freemium — limits (null = unlimited) and current usage
            // so the UI can show "2 of 3" or lock a card without a
            // second roundtrip. Mirrors config/plans.php; mobile stays
            // out of the business of deciding what Pro buys.
            'limits' => [
                'follows' => $this->limit('follows'),
                'saved_searches' => $this->limit('saved_searches'),
                'attended_events_slots' => $this->limit('attended_events_slots'),
                'history_months' => $this->limit('history_months'),
                'export' => $this->allows('export'),
                'household_profiles' => $this->limit('household_profiles'),
            ],
            'usage' => [
                'follows' => $followCount,
                'saved_searches' => $savedSearchCount,
                'attended_events_slots' => $logCount,
            ],
            'remaining' => [
                'follows' => $this->remaining('follows', $followCount),
                'saved_searches' => $this->remaining('saved_searches', $savedSearchCount),
                'attended_events_slots' => $this->remaining('attended_events_slots', $logCount),
            ],

            'email_prefs' => [
                'marketing' => (bool) $this->emails_marketing_enabled,
                'match_alerts' => (bool) $this->emails_match_alerts_enabled,
                'weekly_digest' => (bool) $this->emails_weekly_digest_enabled,
                'product_updates' => (bool) $this->emails_product_updates_enabled,
            ],
        ];
    }
}
