# UX audit — actionable fixes (Sep 2026)

External audit received 2026-09-16. Ranked below by build-order, grouped
into bundles that ship as coherent commits rather than 17 half-things.

Legend: `✅` done · `🟡` in progress · `⬜` pending

---

## Bundle 1 — "Fix the shame" (CSS + Blade only, one commit)

Audit's own "quickest wins with real payoff" list, plus the two things
that are actively misleading users right now.

- ✅ **#7 Kill placeholder strings that leak internal state** — `resources/views/components/event-card.blade.php` no longer renders `'No match banner yet'` or `'No entry link yet'`. When no banner: reticle plate only. When no entry URL: no footer at all.
- ✅ **#6 Drop the duplicate title from the banner** — `.banner-caption` removed from card and CSS. The `h3` in `dope-top` is the sole title.
- ✅ **#17 WCAG AA contrast on card meta** — `--slate` and `--color-slate` darkened from `#76807d` (4.07:1) to `#5a6360` (≈6:1 on white). `.dope-rows dt`, `.dope-date .dow` bumped 10 → 11px. `.dope-top .club` 11 → 12px. `.pill` 9 → 10px.
- ✅ **#9 Whole card is the link** — title `<a class="dope-title-link">` uses `::after { position: absolute; inset: 0 }` to overlay the whole `<article>`. Inner interactive elements (`.dope-primary`, `.dope-save`, save-to-calendar Livewire) lift above the overlay with `position: relative; z-index: 1`. No nested anchors, right-click / keyboard / screen-reader all work.
- ✅ **#10 One primary action per card** — `.dope-primary` (filled brass) renders only when `entry_url` exists. `.dope-save` (ghost) is the secondary. Card body → match detail. Entry button → entry URL. Save-to-calendar → calendar. Three clear jobs, one visual primary.
- ✅ **#4 Branded 404 page** — `resources/views/errors/404.blade.php` with reticle mark + "Off the plate" headline + policy explanation + search form submitting to `/calendar` + 6 recovery link tiles (calendar / discover / clubs / ranges / claim / contact). `500.blade.php` and `503.blade.php` share the same branded shell.

**Acceptance met:** placeholder strings gone from cards, title deduped, contrast passes AA globally (single-var change ripples across the whole app), full card clickable via `::after` overlay, single filled primary button per card only when actionable, `/directory` and any bad URL lands on the branded 404. Shipped in commit ⟨hash below⟩ — 297/297 tests + Pint green.

---

## Bundle 2 — "Fix the filter promise"

- ✅ **#1 Radius filter removed (Option A locked in)** — dropdown gone from `MatchFinder`; `$radius` property deleted; hero search URL no longer includes `?radius=`. Bookmarked `?radius=150` URLs still parse into `CalendarFilter` (`#[Url]` binding stays) so no 500s, but they silently no-op because `PublicEventQuery::applyRadius()` needs a single province + venue coordinates. Restore properly under Bundle 4 (map view) with a real Venue lat/lng migration.
- ✅ **#2 Active-filter chips row** — new `.active-filters` strip inside `CalendarFilter` shows filters that arrived via URL and are not already reflected in the button rows above (discipline + date range). Each chip has its own × click target that clears just that filter (`clearDiscipline`, `clearDates`). Province / family / novice / confirmed already reflected via `aria-pressed` on their respective button rows.
- ✅ **#3 Skeleton chips until hydration** — `.filters { opacity: .55; pointer-events: none }` by default; `resources/js/app.js` adds `.is-hydrated` on `livewire:init`, `DOMContentLoaded`, and `livewire:navigated` / `livewire:morph.updated` so re-morphed chip rows also become interactive. First-click-eaten regression closed.
- ✅ **#14 Result count + Clear filters + empty state** — new `.result-bar` above the grid always shows `N match(es)` with a `— filtered` suffix when any filter is active. `Clear filters` button (calls `clearAll()`) only renders when needed. Empty state now offers Clear filters + Widen the date range CTAs instead of a bare policy sentence.

**Acceptance met:** landing on `/calendar?discipline=ipsc-handgun` renders the "Filtered: IPSC Handgun ×" chip; result count sits above the grid; single Clear filters click resets every filter.

---

## Bundle 3 — "Fix the empty pages"

- ✅ **#11 Discover tiles sorted by upcoming count** — both `HomeController` and `DisciplineController::index` now sort by `events_count DESC, name ASC` via a single-pass `<=>` comparator. Zero-count tiles still render (their discipline page has rules / governance / follow button so it isn't a dead end) but sink to the bottom, and their caption reads "No matches listed yet" in italic slate instead of a bare "0 upcoming". CSS `.disc.is-quiet` mutes name + family colour.
- ✅ **#12 Industry hidden until directory populated (Option A locked in)** — new `Provider::isDirectoryPopulated(int $threshold = 5): bool` (5-minute cache) gates the mobile-menu Industry link, footer Industry link, homepage `dir-col` Industry column, and hero-stats "INDUSTRY 0" chip. Once 5 real published providers exist, all four surfaces flip on automatically. `/suppliers` page itself stays reachable if someone knows the URL.
- ✅ **#13 Ad-slot moved + hide-when-vacant everywhere** — calendar / ranges / suppliers all moved the `<x-ad-slot>` out of the "first thing above filters" slot. `/calendar` now renders it below the whole `<livewire:calendar-filter>`; `/ranges` slots it after the 3rd listing; `/suppliers` puts it under the category grid. All four public content surfaces (home, calendar, ranges, suppliers) now pass `hide-when-vacant` so an unsold slot renders no chrome at all. Vacant "Advertise here" pitch is dead on browsing pages — the `/advertise` sales page carries the rate card.

**Acceptance met:** Discover leads with disciplines that have matches; Industry does not appear anywhere in the public UI until 5+ providers exist; no vacant ad pitch above any list of results.

---

## Bundle 4 — "Cool factor"

Audit's own advice: restraint is the strength, don't add chrome. Three
things move the needle.

- ⬜ **Live reticle (small)**
  - Homepage hero radar SVG gets a slow sweep animation (`@keyframes` CSS, 8s linear infinite, respect `prefers-reduced-motion`)
  - Stretch: range rings scale with the radius dropdown value (only if we build Bundle 2 Option B)
- ⬜ **DOPE-card discipline identity (medium)**
  - `resources/css/app.css` — extend the existing `[data-fam]` selectors so rifle / handgun / shotgun / precision / clay etc. each get a distinctive brass shade or a ballistic-style marker in the top-left corner
  - Consider rendering the spec rows as ballistic table (mono, right-aligned, hairline dividers) instead of the current dt/dd label-value strip
- ⬜ **Map view (largest)**
  - `/calendar?view=map` toggle that renders upcoming matches on a Leaflet map, province-clustered
  - Needs venue lat/lng (see Bundle 2 Option B). Reuse the same migration.
  - This is the screenshot-worthy feature that makes clubs want to be listed.

---

## Bundle 5 — "Scale + a11y polish"

- ⬜ **#15 Mobile: collapse filters to a sticky "Filters (2)" sheet**
  - `resources/views/livewire/calendar-filter.blade.php` — wrap the current block in a details/summary or a Livewire toggle; sticky bar at bottom of viewport on mobile
- ⬜ **#16 Pagination / virtualisation**
  - Convert `PublicEventQuery` result → `->paginate(30)` on `/calendar`; add "Load more" or standard paginator. Only ugly at 400 matches, but we grow into it fast.
- ⬜ **Heading hierarchy (H1 → H3 skip)**
  - Audit each page; convert stray H3s in hero-adjacent sections to H2

---

## Cool-factor stretch (backlog)

- Radar sweep only kicks in when the mouse hovers within the hero radius — quiet by default, alive on intent
- Discipline-family colour on the reticle mark itself in the top nav — subtle brand memorability
- Public "who followed what this week" strip on `/discover` — cheap social proof
- "Nearest match" badge on cards once we have coords

---

## Sequencing recommendation

1. **Bundle 1 first.** Every issue is visible on the first screen a shooter sees, and every fix is CSS/Blade. Half a day of work, biggest reputational payoff.
2. **Bundle 3 next.** Also mostly CSS/Blade + one config-shaped decision (hide Industry vs seed it). Half a day.
3. **Bundle 2** — the state-in-URL work is real code and Livewire hydration polish; option B is a separate multi-day project.
4. **Bundle 4** — after 1–3 are shipped, the cool-factor work lands on solid ground.
5. **Bundle 5** — scale-time housekeeping.
