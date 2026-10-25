import { apiFetch } from './client';
import { EventCard } from './events';

/**
 * One reason a match surfaced in the user's personalised feed.
 *
 * - `type: "club"`      — the shooter follows the hosting organisation
 *                         (regular club, provincial body, or federation).
 * - `type: "discipline"` — the shooter follows this discipline AND the
 *                         match venue is in their home or extra province.
 *
 * The server pre-sorts reasons so clubs appear first and deduplicates
 * on (type, name) — the mobile client renders the array as-is.
 */
export type FeedReason = {
  type: 'club' | 'discipline';
  name: string;
  /**
   * For a club reason: the club's own province. For a discipline reason:
   * the match venue's province. Null when the entity isn't scoped to a
   * province (e.g. national federations like SAPRF have null).
   */
  province: string | null;
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
