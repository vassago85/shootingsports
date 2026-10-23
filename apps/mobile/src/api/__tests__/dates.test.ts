import { formatMatchDate, formatMatchDateFull, splitMatchDate } from '../dates';

/**
 * The date formatters feed MatchCard, MatchRow and the match detail
 * hero. The user caught a bug where 2-day matches showed only the
 * start day, so these tests pin the single/same-month/cross-month
 * variants down.
 */

describe('formatMatchDate (card line)', () => {
  it('formats a single-day match as "WEEKDAY D MON"', () => {
    // 2026-10-25 is a Sunday in Africa/Johannesburg.
    expect(formatMatchDate('2026-10-25T08:00:00+02:00', null)).toBe('SUN 25 OCT');
  });

  it('formats a same-month range with both weekdays and the shared month', () => {
    expect(
      formatMatchDate('2026-11-07T08:00:00+02:00', '2026-11-08T17:00:00+02:00'),
    ).toBe('SAT 7 – SUN 8 NOV');
  });

  it('formats a cross-month range by spelling out both month tags', () => {
    expect(
      formatMatchDate('2026-10-30T08:00:00+02:00', '2026-11-02T17:00:00+02:00'),
    ).toBe('FRI 30 OCT – MON 2 NOV');
  });

  it('ignores ends_at that lands on the same calendar day as starts_at', () => {
    // Day match: 8:00 → 17:00 on the same date collapses to one weekday.
    expect(
      formatMatchDate('2026-10-25T08:00:00+02:00', '2026-10-25T17:00:00+02:00'),
    ).toBe('SUN 25 OCT');
  });

  it('returns TBC when there is no start date', () => {
    expect(formatMatchDate(null, null)).toBe('TBC');
    expect(formatMatchDate(null, '2026-10-25T17:00:00+02:00')).toBe('TBC');
  });
});

describe('splitMatchDate (compact row block)', () => {
  it('returns bare day + month for single-day events', () => {
    expect(splitMatchDate('2026-10-25T08:00:00+02:00', null)).toEqual({
      day: '25',
      month: 'OCT',
    });
  });

  it('joins same-month ranges with an en-dash', () => {
    expect(
      splitMatchDate('2026-11-07T08:00:00+02:00', '2026-11-08T17:00:00+02:00'),
    ).toEqual({ day: '7–8', month: 'NOV' });
  });

  it('shows only the start day/month for cross-month ranges', () => {
    // Cross-month ranges don't fit the 52pt block, so the body meta
    // line is responsible for surfacing the full span.
    expect(
      splitMatchDate('2026-10-30T08:00:00+02:00', '2026-11-02T17:00:00+02:00'),
    ).toEqual({ day: '30', month: 'OCT' });
  });

  it('returns the "— / TBC" placeholder when there is no start date', () => {
    expect(splitMatchDate(null, null)).toEqual({ day: '—', month: 'TBC' });
  });
});

describe('formatMatchDateFull (detail hero)', () => {
  it('writes a single day in long form', () => {
    expect(formatMatchDateFull('2026-10-25T08:00:00+02:00', null)).toContain(
      'Sunday',
    );
    expect(formatMatchDateFull('2026-10-25T08:00:00+02:00', null)).toContain('October');
    expect(formatMatchDateFull('2026-10-25T08:00:00+02:00', null)).toContain('2026');
  });

  it('writes a same-month range with both weekdays and one month+year', () => {
    const label = formatMatchDateFull(
      '2026-11-07T08:00:00+02:00',
      '2026-11-08T17:00:00+02:00',
    );
    expect(label).toContain('Saturday');
    expect(label).toContain('Sunday');
    expect(label).toContain('November');
    expect(label).toContain('7');
    expect(label).toContain('8');
    expect(label).toContain('2026');
  });

  it('writes both months when the range crosses', () => {
    const label = formatMatchDateFull(
      '2026-10-30T08:00:00+02:00',
      '2026-11-02T17:00:00+02:00',
    );
    expect(label).toContain('October');
    expect(label).toContain('November');
  });

  it('returns TBC when there is no start date', () => {
    expect(formatMatchDateFull(null, null)).toBe('TBC');
  });
});
