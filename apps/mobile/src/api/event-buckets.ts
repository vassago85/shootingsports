import { EventCard } from './events';

/**
 * Home-screen event bucketing. Groups the single `/events` fetch
 * into the three sections Home renders: this weekend (Fri→Sun), the
 * next 30 days (ex-weekend), and the "all upcoming" fallback list.
 *
 * The weekend window matches `App\Support\ThisWeekend::range()` on
 * the backend — Mon–Thu rolls to the upcoming Friday, Fri/Sat start
 * today, Sunday is today only. An event qualifies if its date range
 * [starts_at, ends_at] **overlaps** that window — so a Fri+Sat
 * multi-day match correctly surfaces under "This weekend", which is
 * the regression the user caught with Cradock on Fri 23 Oct.
 */

/** Max horizontal cards per section — anything beyond needs the full
 *  Calendar screen with its filters. */
export const CAROUSEL_LIMIT = 6;

/** Max all-upcoming cards shown on Home — keeps the vertical scroll
 *  from eating the whole screen. */
export const ALL_UPCOMING_LIMIT = 6;

/**
 * Fri 00:00 → Sun 23:59 for the user's current week, mirroring the
 * `App\Support\ThisWeekend` rules exactly. `now` is injectable so
 * tests don't drift with the real clock.
 */
export function thisWeekendRange(now: Date = new Date()): { start: Date; end: Date } {
  const dow = now.getDay(); // 0 = Sun, 5 = Fri, 6 = Sat
  const start = new Date(now);
  start.setHours(0, 0, 0, 0);

  // Sunday: window is today only (Fri/Sat already past).
  if (dow === 0) {
    const end = new Date(start);
    end.setHours(23, 59, 59, 999);
    return { start, end };
  }

  // Fri or Sat: window starts today, ends Sunday.
  if (dow === 5 || dow === 6) {
    const sunday = new Date(start);
    sunday.setDate(start.getDate() + (7 - dow));
    sunday.setHours(23, 59, 59, 999);
    return { start, end: sunday };
  }

  // Mon–Thu: jump to the upcoming Friday + full weekend.
  const friday = new Date(start);
  friday.setDate(start.getDate() + (5 - dow));
  const sunday = new Date(friday);
  sunday.setDate(friday.getDate() + 2);
  sunday.setHours(23, 59, 59, 999);
  return { start: friday, end: sunday };
}

/**
 * True if the event's date span intersects `[start, end]`. For
 * single-day events ends_at is null so we treat the end as the start
 * — a Fri-only match shown on a Thursday is "this weekend" iff
 * Friday is included in the window (which it always is in our rule).
 */
function overlaps(
  startsIso: string | null,
  endsIso: string | null,
  windowStart: Date,
  windowEnd: Date,
): boolean {
  if (startsIso === null) {
    return false;
  }

  const starts = new Date(startsIso);
  const ends = endsIso !== null ? new Date(endsIso) : starts;

  return starts <= windowEnd && ends >= windowStart;
}

/**
 * Split a flat events list into the three Home sections. The weekend
 * bucket uses overlap so multi-day Fri+Sat matches land in "This
 * weekend". The next-30 bucket excludes anything already shown in
 * the weekend bucket to avoid two home cards for the same match.
 */
export function bucketEvents(
  events: EventCard[],
  now: Date = new Date(),
): { weekend: EventCard[]; next30: EventCard[]; all: EventCard[] } {
  const weStart = new Date(now);
  weStart.setHours(0, 0, 0, 0);
  const { start: weekendStart, end: weekendEnd } = thisWeekendRange(now);

  const in30End = new Date(weStart);
  in30End.setDate(weStart.getDate() + 30);
  in30End.setHours(23, 59, 59, 999);

  const weekend: EventCard[] = [];
  const next30: EventCard[] = [];
  const seenInWeekend = new Set<number>();

  for (const event of events) {
    if (overlaps(event.starts_at, event.ends_at, weekendStart, weekendEnd)) {
      if (weekend.length < CAROUSEL_LIMIT) {
        weekend.push(event);
      }
      seenInWeekend.add(event.id);
      continue;
    }

    if (overlaps(event.starts_at, event.ends_at, weStart, in30End)) {
      if (!seenInWeekend.has(event.id) && next30.length < CAROUSEL_LIMIT) {
        next30.push(event);
      }
    }
  }

  return {
    weekend,
    next30,
    all: events.slice(0, ALL_UPCOMING_LIMIT),
  };
}
