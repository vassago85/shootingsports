import { apiFetch } from './client';
import { EventCard } from './events';

/**
 * One reason a match surfaced in the user's personalised feed.
 *
 * - `type: "club"`      — the shooter follows the hosting organisation
 *                         (regular club, provincial body, or federation).
 * - `type: "discipline"` — the shooter follows this discipline AND the
 *                         match venue is in their home or extra province.
 * - `type: "near_you"`  — the match venue is within travel_radius_km of
 *                         the shooter's chosen home range. `name` reads
 *                         like "42 km from Alberton Range"; `distance_km`
 *                         carries the raw integer for sorting / testing.
 *
 * The server pre-sorts reasons so clubs appear first and deduplicates
 * on (type, name) — the mobile client renders the array as-is.
 */
export type FeedReason = {
  type: 'club' | 'discipline' | 'near_you';
  name: string;
  /**
   * For a club reason: the club's own province. For a discipline reason:
   * the match venue's province. For a near_you reason: null (distance
   * replaces province as the useful context). Null when the entity
   * isn't scoped to a province (e.g. national federations like SAPRF).
   */
  province: string | null;
  /** Set only on `near_you` reasons. Rounded whole kilometres. */
  distance_km?: number;
};

/**
 * A match in one of the feed's two discovery buckets. Same shape as
 * {@link EventCard} with a `reasons` array appended. The Saved bucket
 * uses plain {@link EventCard} (optionally with `changed: true`).
 */
export type FeedEntry = EventCard & {
  reasons: FeedReason[];
};

export type MeFeedResponse = {
  data: {
    /** Upcoming saved matches, cancellations included (status shows). */
    saved: EventCard[];
    /** Fri–Sun window per server-side HomeWindows::weekend(). */
    this_weekend: FeedEntry[];
    /**
     * Within `travel_radius_km` of the shooter's picked home range.
     * Empty when no home range is set — the mobile UI shows a nudge
     * driven by `meta.has_home_venue` instead of a stale section.
     */
    near_you: FeedEntry[];
    /** Rolling 30-day window per HomeWindows::next30Days(). */
    next_30_days: FeedEntry[];
  };
  meta: {
    /** True when the user follows at least one club or discipline. */
    has_follows: boolean;
    /**
     * True when the user follows a discipline but has no home_province
     * set — the discipline branch of the feed is skipped. The UI nudges
     * the user to pick a province.
     */
    needs_province: boolean;
    /** True when a home range venue is picked (near_you may still be empty). */
    has_home_venue: boolean;
  };
};

/**
 * Fetches the signed-in shooter's personalised home feed. Three
 * buckets (saved, this_weekend, next_30_days) + two meta flags.
 *
 * One request, no pagination — the feed is intentionally bounded by
 * the follow count and the two time windows. See the controller
 * docblock in `App\Http\Controllers\Api\V1\MeFeedController` for the
 * full union logic.
 */
export async function fetchMeFeed(): Promise<MeFeedResponse> {
  return apiFetch<MeFeedResponse>('/me/feed');
}
