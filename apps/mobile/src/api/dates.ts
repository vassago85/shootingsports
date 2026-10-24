/**
 * Date formatting shared by MatchCard, MatchRow, and match detail.
 *
 * These helpers are the single source of truth for how a match's
 * date range is shown anywhere in the app — any bug in two-day match
 * display should land here.
 */

const LOCALE = 'en-ZA';

/** True if the two dates share Year + Month + Day in the device's local tz. */
export function sameYmd(a: Date, b: Date): boolean {
  return (
    a.getFullYear() === b.getFullYear() &&
    a.getMonth() === b.getMonth() &&
    a.getDate() === b.getDate()
  );
}

function weekday(d: Date): string {
  return d.toLocaleDateString(LOCALE, { weekday: 'short' }).toUpperCase();
}

function month(d: Date): string {
  return d.toLocaleDateString(LOCALE, { month: 'short' }).toUpperCase();
}

/**
 * Format a match's date for a card ("SAT 25 OCT"), handling multi-
 * day ranges correctly.
 *
 * - Single day:        "SAT 25 OCT"
 * - Range, same month: "SAT 7 – SUN 8 NOV"
 * - Range, cross-month: "SAT 30 OCT – SUN 2 NOV"
 * - No start_at:       "TBC"
 */
export function formatMatchDate(startIso: string | null, endIso: string | null): string {
  if (startIso === null) {
    return 'TBC';
  }

  const start = new Date(startIso);
  const end = endIso !== null ? new Date(endIso) : null;

  if (end === null || sameYmd(start, end)) {
    return `${weekday(start)} ${start.getDate()} ${month(start)}`;
  }

  if (start.getMonth() === end.getMonth() && start.getFullYear() === end.getFullYear()) {
    return `${weekday(start)} ${start.getDate()} – ${weekday(end)} ${end.getDate()} ${month(end)}`;
  }

  return `${weekday(start)} ${start.getDate()} ${month(start)} – ${weekday(end)} ${end.getDate()} ${month(end)}`;
}

/**
 * Format a match's date as a verbose single line for the detail
 * screen (hero subtitle AND facts "Date" row). Always no comma —
 * the earlier `toLocaleDateString` output produced "Saturday, 31
 * October 2026" which read as two clauses on a hero line already
 * joined by " · ".
 *
 * - Single day:          "Saturday 31 October 2026"
 * - Range, same month:   "Saturday 24 – Sunday 25 October 2026"
 * - Range, cross-month:  "Saturday 31 October – Sunday 1 November 2026"
 * - Range, cross-year:   "Sunday 31 December 2026 – Monday 1 January 2027"
 */
export function formatMatchDateFull(startIso: string | null, endIso: string | null): string {
  if (startIso === null) {
    return 'TBC';
  }

  const start = new Date(startIso);
  const end = endIso !== null ? new Date(endIso) : null;

  const weekdayLong = (d: Date): string =>
    d.toLocaleDateString(LOCALE, { weekday: 'long' });
  const monthLong = (d: Date): string =>
    d.toLocaleDateString(LOCALE, { month: 'long' });

  if (end === null || sameYmd(start, end)) {
    // No comma: assemble the parts manually instead of leaning on
    // toLocaleDateString, which inserts a locale-specific comma for
    // en-ZA.
    return `${weekdayLong(start)} ${start.getDate()} ${monthLong(start)} ${start.getFullYear()}`;
  }

  if (start.getMonth() === end.getMonth() && start.getFullYear() === end.getFullYear()) {
    return `${weekdayLong(start)} ${start.getDate()} – ${weekdayLong(end)} ${end.getDate()} ${monthLong(end)} ${end.getFullYear()}`;
  }

  if (start.getFullYear() === end.getFullYear()) {
    return `${weekdayLong(start)} ${start.getDate()} ${monthLong(start)} – ${weekdayLong(end)} ${end.getDate()} ${monthLong(end)} ${start.getFullYear()}`;
  }

  const fullDate = (d: Date): string =>
    `${weekdayLong(d)} ${d.getDate()} ${monthLong(d)} ${d.getFullYear()}`;
  return `${fullDate(start)} – ${fullDate(end)}`;
}

/**
 * Compact two-row date block for a MatchRow. Returns `{ day, month }`
 * where `day` is "25" for single-day events or "7–8" for a same-month
 * range, and `month` is the end month. Cross-month ranges fall back
 * to the start day/month so the block stays within 52pt wide.
 */
export function splitMatchDate(
  startIso: string | null,
  endIso: string | null,
): { day: string; month: string } {
  if (startIso === null) {
    return { day: '—', month: 'TBC' };
  }

  const start = new Date(startIso);
  const end = endIso !== null ? new Date(endIso) : null;

  if (end === null || sameYmd(start, end)) {
    return { day: String(start.getDate()), month: month(start) };
  }

  if (start.getMonth() === end.getMonth() && start.getFullYear() === end.getFullYear()) {
    return { day: `${start.getDate()}–${end.getDate()}`, month: month(end) };
  }

  // Cross-month ranges don't fit the 52pt block cleanly; show the
  // start and let the body meta line surface the full span.
  return { day: String(start.getDate()), month: month(start) };
}
