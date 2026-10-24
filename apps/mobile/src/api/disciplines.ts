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

/**
 * Fixed display order for the discipline family headers inside the
 * Calendar Filters sheet and the Find Sports tiles. Mirrors the
 * FAMILIES order used on the inline family chip row so every
 * surface reads left-to-right in the same sequence.
 */
export const DISCIPLINE_FAMILY_ORDER: ReadonlyArray<{
  key: string;
  label: string;
}> = [
  { key: 'rifle', label: 'Rifle' },
  { key: 'handgun', label: 'Handgun' },
  { key: 'shotgun', label: 'Shotgun' },
  { key: 'airgun', label: 'Airgun' },
  { key: 'multi', label: 'Multi' },
];

export type DisciplineGroup = {
  key: string;
  label: string;
  disciplines: Discipline[];
};

/**
 * Bucket disciplines under their family header. Shown unconditionally
 * in the Calendar Filters sheet so the user can see every option at
 * once without first having to narrow the family chip row outside the
 * sheet. Families with no disciplines are omitted so empty headers
 * never render.
 */
export function groupDisciplinesByFamily(
  disciplines: Discipline[],
): DisciplineGroup[] {
  const byFamily = new Map<string, Discipline[]>();

  for (const d of disciplines) {
    const key = d.family ?? 'other';
    const bucket = byFamily.get(key) ?? [];
    bucket.push(d);
    byFamily.set(key, bucket);
  }

  const groups = DISCIPLINE_FAMILY_ORDER.filter(
    (f) => (byFamily.get(f.key) ?? []).length > 0,
  ).map((f) => ({
    key: f.key,
    label: f.label,
    disciplines: byFamily.get(f.key) ?? [],
  }));

  const other = byFamily.get('other') ?? [];
  if (other.length > 0) {
    groups.push({ key: 'other', label: 'Other', disciplines: other });
  }

  return groups;
}
