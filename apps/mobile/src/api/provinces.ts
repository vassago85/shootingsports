/**
 * The nine South African provinces, in the order the website's
 * filter uses. These are hardcoded on purpose — the set is fixed by
 * the Constitution of the Republic and does not need a round-trip
 * to the API every time the Calendar renders.
 *
 * `slug` matches `venues.province_slug` on the API, so it can be
 * passed straight into `GET /api/v1/events?province=`.
 */

export type Province = {
  slug: string;
  name: string;
  short: string;
};

export const PROVINCES: Province[] = [
  { slug: 'eastern-cape', name: 'Eastern Cape', short: 'EC' },
  { slug: 'free-state', name: 'Free State', short: 'FS' },
  { slug: 'gauteng', name: 'Gauteng', short: 'GP' },
  { slug: 'kwazulu-natal', name: 'KwaZulu-Natal', short: 'KZN' },
  { slug: 'limpopo', name: 'Limpopo', short: 'LP' },
  { slug: 'mpumalanga', name: 'Mpumalanga', short: 'MP' },
  { slug: 'northern-cape', name: 'Northern Cape', short: 'NC' },
  { slug: 'north-west', name: 'North West', short: 'NW' },
  { slug: 'western-cape', name: 'Western Cape', short: 'WC' },
];

/**
 * Resolve a province slug to its display name. Returns the slug
 * humanised ("western-cape" → "Western Cape") when it isn't one of
 * the known provinces, so a stale deep-link never breaks the UI.
 */
export function provinceNameFromSlug(slug: string): string {
  const match = PROVINCES.find((p) => p.slug === slug);
  if (match !== undefined) {
    return match.name;
  }

  return slug
    .split('-')
    .map((part) => part.charAt(0).toUpperCase() + part.slice(1))
    .join(' ');
}
