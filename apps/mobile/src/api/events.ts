import { apiFetch } from './client';

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
  venue: {
    data: {
      slug: string;
      name: string;
      town: string | null;
      province: string | null;
      lat: number | null;
      lng: number | null;
    } | null;
  } | null;
  host: {
    name: string;
    organisation: { data: { slug: string; name: string } } | null;
  };
  disciplines: { data: Array<{ slug: string; name: string; family: string | null }> };
};

export type EventDetail = EventCard & {
  description: string | null;
  results_url: string | null;
  round_count: number | null;
  target_count: number | null;
  stage_count: number | null;
  venues: {
    data: Array<{ slug: string; name: string; town: string | null; province: string | null; lat: number | null; lng: number | null }>;
  };
};

export type EventFilters = {
  family?: string | null;
  discipline?: string | null;
  province?: string | null;
  from?: string | null;
  to?: string | null;
  novice?: boolean;
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

export async function listEvents(filters: EventFilters = {}): Promise<{
  events: EventCard[];
  count: number;
}> {
  const response = await apiFetch<{ data: EventCard[]; meta: { count: number } }>(
    `/events${toQuery(filters)}`,
    { auth: false },
  );

  return { events: response.data, count: response.meta.count };
}

export async function fetchEvent(slug: string): Promise<EventDetail> {
  const response = await apiFetch<{ data: EventDetail }>(`/events/${slug}`, { auth: false });
  return response.data;
}
