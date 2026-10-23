# Match Title Normalisation

**Status:** Backlog
**Opened by:** mobile device-test review, 22 Oct 2026
**Owner:** TBC
**Target:** Platform data cleanup + import-time rules

## Problem

Discipline and match titles entered by match directors on the web are
inconsistent. On the Shooting Sports mobile Calendar, the user sees
sibling events titled:

- `CentreFire 1Day`
- `Centrefire`
- `Centerfire` (variant observed in older data)

This looks like a bug in the client, but it's faithful to the data —
every variant is a real `events.title` row. The mobile app renders
whatever ships from `/api/v1/events`, so the fix belongs on the
platform side.

## Scope

1. **One-time sweep** — write a Pest-covered command
   (`php artisan events:normalise-titles`) that:
   - Collapses obvious variants (`CentreFire`/`Centrefire`/`Center Fire`
     → `Centrefire`, `1Day`/`1-Day`/`1 day` → `1-Day`, `Full-Bore`/
     `Fullbore`/`Full Bore` → `Fullbore`, etc.)
   - Logs every change with before/after + event id for review
   - Is idempotent (safe to re-run)
   - Takes `--dry-run` and `--only=<id,id>`
2. **Import-time rules** — add `TitleNormaliser` (or extend the
   existing event sanitiser) so new events saved via Filament or
   imported from the website apply the same rules automatically.
3. **Discipline-term canonical list** — pull the master list from
   `disciplines.name` + an `aliases` array (JSON column). The
   normaliser swaps any alias found in the title back to the canonical
   name.

## Non-goals

- Translating titles (keep the match director's phrasing minus the
  variant spelling).
- Changing display logic on mobile. All normalisation happens
  server-side; the mobile client stays dumb.

## Risks

- A match director's intentional casing (e.g. a club named
  "CentreFire Classic" where they want "CentreFire") gets flattened.
  Mitigation: the alias map is explicit, not a fuzzy matcher. Only
  the discipline fragment is touched, not the surrounding title.

## Tests

- `tests/Feature/EventTitleNormaliserTest.php` covering every
  rule + idempotency + leave-it-alone cases.
- `tests/Feature/EventsNormaliseTitlesCommandTest.php` covering
  `--dry-run`, logging, and `--only` scoping.

## When to pick this up

Not blocking app ship. Pick up when:
- A sweep of `events.title` surfaces > 20 variant pairs, OR
- A UI consumer (mobile Calendar, website filter facets) is confused
  enough that users complain.
