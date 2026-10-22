import { PROVINCES, provinceNameFromSlug } from '../provinces';

/**
 * The province lookup is used anywhere the app receives a province
 * slug from a route param or an API filter and needs a readable
 * label — Calendar's chip strip, Find's deep-link chip, Me's home-
 * province pill. If the slug lookup breaks, every one of those labels
 * silently falls back to "Western-cape" instead of "Western Cape".
 */
describe('provinceNameFromSlug', () => {
  it('returns the display name for every known province', () => {
    for (const p of PROVINCES) {
      expect(provinceNameFromSlug(p.slug)).toBe(p.name);
    }
  });

  it('humanises an unknown slug instead of returning it raw', () => {
    expect(provinceNameFromSlug('made-up-region')).toBe('Made Up Region');
    expect(provinceNameFromSlug('single')).toBe('Single');
  });

  it('is the full set of nine SA provinces, in alphabetical order', () => {
    const names = PROVINCES.map((p) => p.name);
    expect(names).toEqual([
      'Eastern Cape',
      'Free State',
      'Gauteng',
      'KwaZulu-Natal',
      'Limpopo',
      'Mpumalanga',
      'Northern Cape',
      'North West',
      'Western Cape',
    ]);
  });

  it('uses the slug shape the backend emits for venue.province_slug', () => {
    // These three are the gotchas: lowercase, hyphenated, no spaces.
    // Any backend change away from this would break Calendar's
    // province chip + Find's deep link in one go.
    expect(PROVINCES.find((p) => p.name === 'KwaZulu-Natal')?.slug).toBe('kwazulu-natal');
    expect(PROVINCES.find((p) => p.name === 'North West')?.slug).toBe('north-west');
    expect(PROVINCES.find((p) => p.name === 'Western Cape')?.slug).toBe('western-cape');
  });
});
