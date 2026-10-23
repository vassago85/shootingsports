import { apiFetch } from './client';

/**
 * Published organisation (club, series, association, provincial
 * body) shape returned by `GET /api/v1/organisations`. Mirrors
 * `App\Http\Resources\Api\V1\OrganisationResource`.
 *
 * Default scope on the controller is `clubs()` which excludes
 * federations. Pass `type=federation` to opt in if a future screen
 * needs them.
 */
export type Organisation = {
  id: number;
  slug: string;
  name: string;
  short_name: string | null;
  type: string | null;
  town: string | null;
  province: string | null;
  province_slug: string | null;
  logo_url: string | null;
  url: string;
  visitors_welcome: boolean;
  disciplines: Array<{ slug: string; name: string }>;
};

export type OrganisationFilters = {
  province?: string | null;
  /** Free-text name/town search. Server does a simple LIKE match. */
  q?: string | null;
  /** Opt in to visitors-only clubs. Defaults to false (shows all). */
  visitors?: boolean;
  /** Override the default `clubs()` scope: `federation`, `series`, etc. */
  type?: string | null;
};

function toQuery(filters: OrganisationFilters): string {
  const params = new URLSearchParams();

  for (const [key, value] of Object.entries(filters)) {
    if (value === null || value === undefined || value === '' || value === false) {
      continue;
    }
    params.append(key, String(value));
  }

  const query = params.toString();
  return query.length > 0 ? `?${query}` : '';
}

export async function listOrganisations(
  filters: OrganisationFilters = {},
): Promise<Organisation[]> {
  const response = await apiFetch<{ data: Organisation[] }>(
    `/organisations${toQuery(filters)}`,
    { auth: false },
  );
  return response.data;
}
