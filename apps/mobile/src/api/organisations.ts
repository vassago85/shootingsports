import { apiFetch } from './client';

/**
 * Published organisation (club, series, association, provincial
 * body, federation) shape returned by `GET /api/v1/organisations`.
 * Mirrors `App\Http\Resources\Api\V1\OrganisationResource`.
 *
 * The default scope now covers every membership-adjacent type so
 * federations like SAPRF and province-less associations like
 * NRL Hunter SA surface alongside clubs. The `group` field tells
 * the client which section to drop a row into: "national" bodies
 * render above the province groups on the Clubs tab.
 */
export type OrganisationGroup = 'national' | 'regional';

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
  group: OrganisationGroup;
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
