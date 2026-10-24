import { apiFetch } from './client';

/**
 * Shape of nested venue, host.organisation and disciplines mirrors the
 * live `GET /api/v1/events` response. The API returns flat arrays and
 * objects — no `{data: ...}` wrappers around relations. Only the
 * **top-level** envelope is wrapped in `{data: ..., meta: ...}`.
 */

export type EventVenue = {
  slug: string;
  name: string;
  town: string | null;
  province: string | null;
  province_slug: string | null;
  lat: number | null;
  lng: number | null;
  /** Street address for the match detail Directions button fallback when no lat/lng pin exists. */
  address: string | null;
};

export type EventDiscipline = {
  slug: string;
  name: string;
  family: string | null;
};

export type EventCard = {
  id: number;
  slug: string;
  title: string;
  starts_at: string | null;
  ends_at: string | null;
  all_day: boolean;
  level: string | null;
  status: string | null;
  entry_url: string | null;
  entry_fee_cents: number | null;
  member_fee_cents: number | null;
  location_label: string;
  banner_url: string | null;
  url: string;
  novice_friendly: boolean | null;
  venue: EventVenue | null;
  host: {
    name: string;
    organisation: {
      slug: string;
      name: string;
      short_name: string | null;
      logo_url: string | null;
    } | null;
  };
  disciplines: EventDiscipline[];
};

export type EventDetail = EventCard & {
  description: string | null;
  results_url: string | null;
  round_count: number | null;
  target_count: number | null;
  stage_count: number | null;
  venues: EventVenue[];
};

export type EventFilters = {
  family?: string | null;
  discipline?: string | null;
  province?: string | null;
  /**
   * Venue slug — scopes the list to a single range. The backend
   * dual-reads from the legacy `events.venue_id` column AND the
   * `event_venue` pivot so Day 2 ranges on a multi-venue match are
   * also included. See `PublicEventQuery::builder()`.
   */
  venue?: string | null;
  from?: string | null;
  to?: string | null;
  novice?: boolean;
  /**
   * Fri-Sun window using `App\Support\ThisWeekend::range()`. Mutually
   * exclusive with `from` / `to` (if both set the server uses weekend).
   * Prefer this flag over computing dates client-side so the Mon-Thu
   * "upcoming Friday" and the Sat-today edge cases stay in sync with
   * the website calendar.
   */
  weekend?: boolean;
  confirmed?: boolean;
  radius?: number | null;
  near?: string | null;
  lat?: number | null;
  lng?: number | null;
  limit?: number;
};

function toQuery(filters: EventFilters): string {
  const params = new URLSearchParams();

  for (const [key, value] of Object.entries(filters)) {
    if (value === null || value === undefined || value === '') {
      continue;
    }
    params.append(key, String(value));
  }

  const query = params.toString();
  return query.length > 0 ? `?${query}` : '';
}

/**
 * Keys the API's empty-state copilot can suggest removing to recover
 * matches. The server returns a filter key and a count ("remove this
 * to see N matches"); the client owns the display copy so wording
 * stays aligned with the chip label the user is looking at.
 */
export type EventFilterSuggestionKey =
  | 'novice'
  | 'weekend'
  | 'from'
  | 'to'
  | 'province'
  | 'discipline'
  | 'family'
  | 'confirmed'
  | 'kind';

export type EventFilterSuggestion = {
  filter: EventFilterSuggestionKey;
  would_show: number;
};

export async function listEvents(filters: EventFilters = {}): Promise<{
  events: EventCard[];
  count: number;
  suggestion: EventFilterSuggestion | null;
}> {
  const response = await apiFetch<{
    data: EventCard[];
    meta: { count: number; suggestion: EventFilterSuggestion | null };
  }>(`/events${toQuery(filters)}`, { auth: false });

  return {
    events: response.data,
    count: response.meta.count,
    suggestion: response.meta.suggestion ?? null,
  };
}

export async function fetchEvent(slug: string): Promise<EventDetail> {
  const response = await apiFetch<{ data: EventDetail }>(`/events/${slug}`, { auth: false });
  return response.data;
}
