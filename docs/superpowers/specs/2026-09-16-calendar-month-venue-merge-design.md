# Calendar month view + venue merge (16 Sep 2026)

Approved approach: one cohesive slice — public month grid beside List/Map, Filament venue merge with duplicate suggestions, and pasteable lat/lng.

## Decisions

| Topic | Choice |
| --- | --- |
| Calendar shape | Month grid (classic wall calendar) |
| Filters | Same Livewire filters as list; three-way toggle List \| Month \| Map |
| Match window | Upcoming only (`Event::upcoming()` / `PublicEventQuery`) |
| Venue merge | Admin-only absorb into primary + possible-duplicates list |
| Loser venues | Soft-archive (`status = archived`), not hard-delete |
| Coordinates | Paste `-25.95, 28.48` → lat/lng + `geocode_source = staff` |

Homepage gold strip already counts `Event::upcoming()`; keep that rule. Optional label clarity (“Upcoming”) is fine but not required for this slice.

## Section 1 — Month calendar (public)

### Route & chrome
- Route: `GET /calendar/month` → `calendar.month`.
- Hero view toggle on list, month, and map: **List | Month | Map** (plain anchors, `aria-pressed`).
- Reuse `CalendarFilter` Livewire (or a thin month wrapper that shares the same query props) so family / province / discipline / near / radius / confirmed / from–to stay in sync via the URL.

### Month chrome
- Prev / next month + “Today”.
- Visible month is independent of from/to filters for navigation; if from is set, initial month is the month containing `from`, else current month.
- Only matches with `starts_at` inside the visible month **and** still upcoming are shown (intersection of month window ∩ upcoming scope ∩ filters).

### Grid
- Mon–Sun columns; cells for days in the month (leading/trailing padding days muted, no chips).
- Compact chips per match: truncated title + town; click → match page.
- Overflow: “+N more” expands in-cell or links to list filtered to that day.
- Empty days stay quiet.
- Mobile: same grid, smaller type; chips stack inside the cell — no horizontal page scroll.

### Data
- `PublicEventQuery` only; no past / completed / cancelled / draft; host org must be published (existing upcoming rules).

### Out of scope (calendar)
- Week view, drag-and-drop, iCal export of the month grid, road routing.

## Section 2 — Venue merge + paste coords (Filament)

### Merge behaviour
- Admin Venue resource: bulk “Merge into…” and/or Edit-page action.
- Operator picks **primary** and one or more **duplicates** (primary ≠ any loser).
- In a DB transaction:
  1. Reassign `events.venue_id` from losers → primary.
  2. Fill empty primary fields from losers where useful (town, address, province, access, etc.).
  3. Pin policy: primary staff pin wins; if primary has no lat/lng, adopt the best loser pin (prefer `geocode_source = staff`, else any pin) and copy `geocode_source` / `geocoded_at`.
  4. Set each loser `status = archived` (SoftDeletes unchanged — trash is a separate recovery path).
- Success notification: “N events moved, M venues archived”.

### Possible duplicates
- Filament page or Venue list filter **Likely duplicates**.
- Heuristic: normalised name + same town, and/or similar name within the same province (simple similarity / shared tokens — no ML).
- Each group: open records + “Merge into A” / “Merge into B”.

### Paste coordinates
- Venue form field **Paste coordinates** accepting:
  - `-25.952912873030343, 28.486456735843475`
  - variants with spaces, optional degree symbols
- On paste/blur: parse → set `lat`, `lng`, `geocode_source = staff`, `geocoded_at = now()`; clear the paste field.
- Keep discrete lat/lng inputs for fine edits (existing staff-lock on save remains).

### Out of scope (venues)
- Public self-serve merge, Google Geocoding swap (still later behind `Geocoder`), provider geocoding, hard-delete of losers as the default path.

## Testing (acceptance)

- Month route renders grid; toggle works from List and Map.
- Filters narrow chips; only upcoming matches appear.
- Merge moves events, archives losers, preserves staff pin on primary.
- Duplicate suggestions surface known near-duplicates in fixtures.
- Paste string fills lat/lng and staff-locks.
- Existing Pest calendar / map / geocode suites stay green.

## Deploy notes

- No new env keys for this slice.
- After deploy: rebuild app containers as usual; no special artisan beyond normal migrate if any schema changes land for merge audit (prefer no schema unless we add a `merged_into_venue_id` — **not required for v1**; archive + event reassignment is enough).
