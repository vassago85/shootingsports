import { apiFetch } from './client';

/**
 * Discipline shape returned by `GET /api/v1/disciplines`. Mirrors
 * `App\Http\Resources\Api\V1\DisciplineResource`.
 *
 * `family` is the firearm family (rifle / handgun / shotgun / airgun /
 * multi) that groups chip rows on Calendar. `upcoming_count` is used
 * to hide empty disciplines from the filter row so the user never sees
 * a chip that would return zero matches.
 */
export type Discipline = {
  id: number;
  slug: string;
  name: string;
  family: string | null;
  family_label: string | null;
  short_blurb: string | null;
  parent_id: number | null;
  upcoming_count: number;
  url: string;
};

/**
 * Fetch top-level (parent-null) disciplines. Only the "active" set
 * (ones with at least 1 upcoming match) is useful for the Calendar
 * chip row — call `activeDisciplines()` for that filtered view.
 */
export async function listDisciplines(): Promise<Discipline[]> {
  const response = await apiFetch<{ data: Discipline[] }>('/disciplines', {
    auth: false,
  });
  return response.data;
}

/**
 * Disciplines with at least one upcoming match, grouped so the
 * Calendar can show only families and disciplines that will actually
 * return results. Preserves the sort order from the API (which uses
 * `sort_order` server-side).
 */
export function activeDisciplines(all: Discipline[]): Discipline[] {
  return all.filter((d) => d.upcoming_count > 0);
}
