import { apiFetch } from './client';

/**
 * Published venue ("range") shape returned by `GET /api/v1/venues`.
 * Mirrors `App\Http\Resources\Api\V1\VenueResource`.
 *
 * `logo_url`, `summary`, `disciplines` and `upcoming_count` are
 * additions for the Find Ranges list; the venue payload shipped on
 * event cards (via EventResource) also carries them now because
 * they're null-safe and cheap when the relation is not loaded.
 */
export type Venue = {
  id: number;
  slug: string;
  name: string;
  town: string | null;
  province: string | null;
  province_slug: string | null;
  lat: number | null;
  lng: number | null;
  url: string;
  logo_url: string | null;
  summary: string | null;
  disciplines: Array<{ slug: string; name: string }>;
  upcoming_count: number;
};

export type VenueFilters = {
  province?: string | null;
  /** Free-text name/town search. Server does a simple LIKE match. */
  q?: string | null;
};

function toQuery(filters: VenueFilters): string {
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

export async function listVenues(filters: VenueFilters = {}): Promise<Venue[]> {
  const response = await apiFetch<{ data: Venue[] }>(
    `/venues${toQuery(filters)}`,
    { auth: false },
  );
  return response.data;
}
