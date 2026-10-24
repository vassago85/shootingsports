import {
  activeDisciplines,
  Discipline,
  groupDisciplinesByFamily,
} from '../disciplines';

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

/**
 * `groupDisciplinesByFamily` powers the Calendar Filters sheet and
 * the Find Sports grid. The user asked us to stop hiding disciplines
 * behind the family chip — this helper is how we show every option
 * at once, grouped under a family header.
 */
describe('groupDisciplinesByFamily', () => {
  it('groups disciplines under the Rifle / Handgun / Shotgun / Airgun / Multi order', () => {
    const rows = [
      makeDiscipline({
        slug: 'ipsc',
        name: 'IPSC',
        family: 'handgun',
        family_label: 'Handgun',
      }),
      makeDiscipline({
        slug: 'prs',
        name: 'PRS',
        family: 'rifle',
        family_label: 'Rifle',
      }),
      makeDiscipline({
        slug: 'skeet',
        name: 'Skeet',
        family: 'shotgun',
        family_label: 'Shotgun',
      }),
    ];

    const groups = groupDisciplinesByFamily(rows);

    expect(groups.map((g) => g.key)).toEqual(['rifle', 'handgun', 'shotgun']);
    expect(groups[0]!.disciplines.map((d) => d.slug)).toEqual(['prs']);
    expect(groups[1]!.disciplines.map((d) => d.slug)).toEqual(['ipsc']);
    expect(groups[2]!.disciplines.map((d) => d.slug)).toEqual(['skeet']);
  });

  it('omits family headers that have no disciplines', () => {
    const rows = [
      makeDiscipline({ slug: 'prs', family: 'rifle', family_label: 'Rifle' }),
    ];

    const groups = groupDisciplinesByFamily(rows);

    expect(groups.map((g) => g.key)).toEqual(['rifle']);
  });

  it('still shows a discipline with zero upcoming (browse surface)', () => {
    // Calendar's chip row filters out ghost chips via `activeDisciplines`.
    // The Sports / Filters-sheet surface uses THIS helper directly,
    // without filtering, so a zero-upcoming discipline still renders.
    const rows = [
      makeDiscipline({
        slug: 'quiet-rifle',
        family: 'rifle',
        upcoming_count: 0,
      }),
    ];

    const groups = groupDisciplinesByFamily(rows);
    expect(groups[0]!.disciplines).toHaveLength(1);
  });

  it('buckets an unknown family under "Other" at the end', () => {
    const rows = [
      makeDiscipline({ slug: 'prs', family: 'rifle' }),
      makeDiscipline({ slug: 'mystery', family: null }),
    ];

    const groups = groupDisciplinesByFamily(rows);

    expect(groups.map((g) => g.key)).toEqual(['rifle', 'other']);
    expect(groups[1]!.label).toBe('Other');
    expect(groups[1]!.disciplines.map((d) => d.slug)).toEqual(['mystery']);
  });

  it('returns an empty array when given no disciplines', () => {
    expect(groupDisciplinesByFamily([])).toEqual([]);
  });
});
