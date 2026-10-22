import { activeDisciplines, Discipline } from '../disciplines';

/** Factory for a discipline row with the fields the chip row touches. */
function makeDiscipline(partial: Partial<Discipline>): Discipline {
  return {
    id: 1,
    slug: 'x',
    name: 'X',
    family: 'rifle',
    family_label: 'Rifle',
    short_blurb: null,
    parent_id: null,
    upcoming_count: 0,
    url: 'https://example.com',
    ...partial,
  };
}

/**
 * `activeDisciplines` keeps the Calendar chip row free of "ghost"
 * chips that would return zero matches on tap. If this ever stops
 * filtering, every discipline from the backend shows up — including
 * the deliberately empty ones used to warm the taxonomy.
 */
describe('activeDisciplines', () => {
  it('drops rows with zero upcoming matches', () => {
    const rows = [
      makeDiscipline({ slug: 'prs', upcoming_count: 6 }),
      makeDiscipline({ slug: 'empty-sport', upcoming_count: 0 }),
      makeDiscipline({ slug: 'ipsc', upcoming_count: 7 }),
    ];
    const active = activeDisciplines(rows);
    expect(active.map((d) => d.slug)).toEqual(['prs', 'ipsc']);
  });

  it('preserves API order so the server-side sort_order wins', () => {
    const rows = [
      makeDiscipline({ slug: 'nrl-hunter', upcoming_count: 4 }),
      makeDiscipline({ slug: 'prs', upcoming_count: 6 }),
      makeDiscipline({ slug: 'hunting-rifle', upcoming_count: 3 }),
    ];
    expect(activeDisciplines(rows).map((d) => d.slug)).toEqual([
      'nrl-hunter',
      'prs',
      'hunting-rifle',
    ]);
  });

  it('returns an empty list when nothing is upcoming', () => {
    const rows = [
      makeDiscipline({ slug: 'a', upcoming_count: 0 }),
      makeDiscipline({ slug: 'b', upcoming_count: 0 }),
    ];
    expect(activeDisciplines(rows)).toEqual([]);
  });
});
