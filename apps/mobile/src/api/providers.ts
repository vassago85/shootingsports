import { apiFetch } from './client';

/**
 * Published provider ("shop" / supplier) shape returned by
 * `GET /api/v1/providers`. Mirrors `App\Http\Resources\Api\V1\
 * ProviderResource`.
 *
 * Distributor-only listings are excluded server-side — they're
 * trade-only advertising accounts, not shopping destinations.
 */
export type Provider = {
  id: number;
  slug: string;
  name: string;
  category: string | null;
  category_label: string | null;
  town: string | null;
  province: string | null;
  province_slug: string | null;
  tagline: string | null;
  summary: string | null;
  logo_url: string | null;
  tier: string | null;
  url: string;
};

/**
 * Public (non-distributor) categories a visitor can filter the Shops
 * list by. Order mirrors `ProviderCategory::publicCases()` on the
 * server. Keep this list in sync when the enum grows.
 */
export const PROVIDER_CATEGORIES: ReadonlyArray<{ slug: string; label: string }> = [
  { slug: 'gunsmith', label: 'Gunsmith' },
  { slug: 'dealer', label: 'Dealer' },
  { slug: 'instructor', label: 'Instructor' },
  { slug: 'reloading-components', label: 'Reloading' },
  { slug: 'ammunition', label: 'Ammunition' },
  { slug: 'optics', label: 'Optics' },
  { slug: 'chassis-stocks', label: 'Chassis' },
  { slug: 'ballistics-electronics', label: 'Ballistics' },
  { slug: 'range-equipment', label: 'Range gear' },
  { slug: 'safes-storage', label: 'Safes' },
  { slug: 'transport', label: 'Transport' },
  { slug: 'insurance', label: 'Insurance' },
];

export type ProviderFilters = {
  province?: string | null;
  /** URL-slug form of a `ProviderCategory` (e.g. `reloading-components`). */
  category?: string | null;
  q?: string | null;
};

function toQuery(filters: ProviderFilters): string {
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

export async function listProviders(
  filters: ProviderFilters = {},
): Promise<Provider[]> {
  const response = await apiFetch<{ data: Provider[] }>(
    `/providers${toQuery(filters)}`,
    { auth: false },
  );
  return response.data;
}
