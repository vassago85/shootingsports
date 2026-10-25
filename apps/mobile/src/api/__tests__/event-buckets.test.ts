import { EventCard } from '../events';
import { bucketEvents, thisWeekendRange } from '../event-buckets';

/**
 * Minimal event factory. Only the fields the bucket inspects are
 * meaningful; everything else is noise padding.
 */
function makeEvent(partial: {
  id: number;
  starts_at: string | null;
  ends_at?: string | null;
}): EventCard {
  return {
    id: partial.id,
    slug: `event-${partial.id}`,
    title: 'Match',
    starts_at: partial.starts_at,
    ends_at: partial.ends_at ?? null,
    all_day: true,
    level: null,
    status: null,
    entry_url: null,
    entry_fee_cents: null,
    member_fee_cents: null,
    location_label: 'Somewhere',
    banner_url: null,
    url: 'https://example.com',
    novice_friendly: null,
    venue: null,
    host: { name: 'Host', organisation: null },
    disciplines: [],
    last_material_change_at: null,
  };
}

/*
 * --------------------------------------------------------------------
 * thisWeekendRange
 * --------------------------------------------------------------------
 */

describe('thisWeekendRange', () => {
  it('on a Monday (Oct 19 2026) rolls to the upcoming Fri → Sun', () => {
    const monday = new Date(2026, 9, 19, 10, 0); // Oct 19 2026, Mon
    const { start, end } = thisWeekendRange(monday);
    // Friday 23 Oct 00:00 → Sunday 25 Oct 23:59.
    expect(start.getDate()).toBe(23);
    expect(start.getDay()).toBe(5);
    expect(end.getDate()).toBe(25);
    expect(end.getDay()).toBe(0);
  });

  it('on a Thursday still rolls to the upcoming Friday', () => {
    const thursday = new Date(2026, 9, 22, 10, 0); // Oct 22 2026, Thu
    const { start, end } = thisWeekendRange(thursday);
    expect(start.getDate()).toBe(23);
    expect(end.getDate()).toBe(25);
  });

  it('on a Friday starts today, ends Sunday', () => {
    const friday = new Date(2026, 9, 23, 10, 0); // Oct 23 2026, Fri
    const { start, end } = thisWeekendRange(friday);
    expect(start.getDate()).toBe(23);
    expect(end.getDate()).toBe(25);
  });

  it('on a Saturday starts today, ends Sunday', () => {
    const saturday = new Date(2026, 9, 24, 10, 0); // Oct 24 2026, Sat
    const { start, end } = thisWeekendRange(saturday);
    expect(start.getDate()).toBe(24);
    expect(end.getDate()).toBe(25);
  });

  it('on a Sunday the window collapses to today only', () => {
    const sunday = new Date(2026, 9, 25, 10, 0); // Oct 25 2026, Sun
    const { start, end } = thisWeekendRange(sunday);
    expect(start.getDate()).toBe(25);
    expect(end.getDate()).toBe(25);
  });
});

/*
 * --------------------------------------------------------------------
 * bucketEvents
 * --------------------------------------------------------------------
 */

describe('bucketEvents', () => {
  const thursday = new Date(2026, 9, 22, 10, 0); // Oct 22 2026, Thu

  it('includes a single-day Friday match in "This weekend"', () => {
    // The exact case the user caught: Cradock Fri 23 Oct showed up
    // under Next 30 days but not This weekend.
    const events = [makeEvent({ id: 1, starts_at: '2026-10-23T08:30:00+02:00' })];
    const { weekend, next30 } = bucketEvents(events, thursday);
    expect(weekend.map((e) => e.id)).toEqual([1]);
    expect(next30.map((e) => e.id)).toEqual([]);
  });

  it('includes a Fri+Sat multi-day match in "This weekend"', () => {
    const events = [
      makeEvent({
        id: 1,
        starts_at: '2026-10-23T08:00:00+02:00',
        ends_at: '2026-10-24T17:00:00+02:00',
      }),
    ];
    const { weekend } = bucketEvents(events, thursday);
    expect(weekend.map((e) => e.id)).toEqual([1]);
  });

  it('excludes a Thursday match from "This weekend" (window starts Fri)', () => {
    const events = [makeEvent({ id: 1, starts_at: '2026-10-22T08:00:00+02:00' })];
    const { weekend, next30 } = bucketEvents(events, thursday);
    expect(weekend).toEqual([]);
    // Thursday today starts the 30-day window — the event is "today".
    expect(next30.map((e) => e.id)).toEqual([1]);
  });

  it('places a Monday match two weeks out into "Next 30 days", not weekend', () => {
    const events = [makeEvent({ id: 1, starts_at: '2026-11-02T08:00:00+02:00' })];
    const { weekend, next30 } = bucketEvents(events, thursday);
    expect(weekend).toEqual([]);
    expect(next30.map((e) => e.id)).toEqual([1]);
  });

  it('does not duplicate a weekend event into "Next 30 days"', () => {
    const events = [makeEvent({ id: 1, starts_at: '2026-10-24T08:00:00+02:00' })];
    const { weekend, next30 } = bucketEvents(events, thursday);
    expect(weekend.map((e) => e.id)).toEqual([1]);
    expect(next30).toEqual([]);
  });

  it('always exposes the full upcoming list as `all` (first six)', () => {
    const events = Array.from({ length: 8 }, (_, i) =>
      makeEvent({ id: i + 1, starts_at: '2026-11-05T08:00:00+02:00' }),
    );
    const { all } = bucketEvents(events, thursday);
    expect(all.map((e) => e.id)).toEqual([1, 2, 3, 4, 5, 6]);
  });

  it('skips events with no start date', () => {
    const events = [
      makeEvent({ id: 1, starts_at: null }),
      makeEvent({ id: 2, starts_at: '2026-10-24T08:00:00+02:00' }),
    ];
    const { weekend, next30 } = bucketEvents(events, thursday);
    expect(weekend.map((e) => e.id)).toEqual([2]);
    expect(next30).toEqual([]);
  });
});
