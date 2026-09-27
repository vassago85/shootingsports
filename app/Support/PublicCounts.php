<?php

namespace App\Support;

use App\Enums\OrganisationType;
use App\Models\Discipline;
use App\Models\Event;
use App\Models\Organisation;
use App\Models\Provider;
use App\Models\Venue;
use Illuminate\Support\Facades\Cache;

/**
 * Single source of truth for public-facing counts (clubs, ranges,
 * matches, disciplines, suppliers, provinces). Templates that used
 * to hard-code numbers or run their own queries should call this so
 * the number in the hero, the number in the footer, and the number
 * in the empty state can never drift apart.
 *
 * Cached under `public.stats` with a short TTL — the same key that
 * gets invalidated by {@see PublicCache::bump()} when listings
 * change, so a new club or match propagates within one cache cycle.
 */
class PublicCounts
{
    /**
     * @return array<string, int>
     */
    public static function all(): array
    {
        return Cache::remember(PublicCache::key('stats'), 600, function (): array {
            return [
                // Membership clubs and series are counted separately so
                // the hero can read "34 clubs · 12 series" without
                // conflating the two mental models a shooter has.
                'clubs' => Organisation::query()->published()->where('type', OrganisationType::Club)->count(),
                'series' => Organisation::query()->published()->where('type', OrganisationType::Series)->count(),
                'ranges' => Venue::query()->published()->count(),
                // Count what visitors can actually reach on /calendar
                // right now. `published()` would inflate this with
                // Completed/Cancelled rows a visitor cannot open.
                'matches' => Event::query()->upcoming()->count(),
                'disciplines' => Discipline::query()->where('is_published', true)->count(),
                // Provinces is a constant, not a query. Nine provinces
                // in South Africa, none of them optional.
                'provinces' => 9,
                'suppliers' => Provider::query()->published()->listed()->count(),
            ];
        });
    }

    public static function get(string $key): int
    {
        return self::all()[$key] ?? 0;
    }
}
