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

The hero sells "find your next match" and the filter is the only surface
that delivers on it. Right now it lies twice (radius does nothing, chips
don't reflect state).

- ⬜ **#1 Radius filter is a no-op — decide**
  - **Option A (recommended, quick):** Delete the radius dropdown from the hero. The site does not know where the user is and does not have venue coordinates on every range. Sell what we deliver — province + discipline — and stop implying a geo query.
  - **Option B (slow, high-value):** Add "use my location" prompt + venue lat/lng on `Venue` model + Haversine scope + radius chip. New migration, new geolocation UX, new privacy copy. 2–3 day build.
- ⬜ **#2 Hero filters render as active chips on arrival**
  - `app/Livewire/CalendarFilter.php` — read query params in `mount()` and set filter state (already does province, needs discipline + level + status)
  - Chip row in `resources/views/livewire/calendar-filter.blade.php` — render active state per filter, don't hard-code "ALL"
- ⬜ **#3 Skeleton chips until Livewire hydrates**
  - Add `wire:offline` / `x-cloak`-style hide OR render the chips with `[disabled]` + a shimmer until `livewire:init` fires
  - Simplest: `.chip { pointer-events: none } .chip.is-hydrated { pointer-events: auto }` and add the class on `Livewire.on('...')` boot
- ⬜ **#14 Result count + Clear filters**
  - Above the grid: `<p class="result-count">{{ $count }} match{{ $count === 1 ? '' : 'es' }}</p>` + `<button type="button" wire:click="reset">Clear filters</button>` (only when any filter is active)
  - Empty state gets a "Widen date range" and "Nearest matches instead" CTA on top of the existing policy line

**Acceptance:** landing on `/calendar?province=gauteng&discipline=ipsc-handgun` shows both chips lit; the number of results is visible above the grid; a clear-filters button removes them all in one click.

---

## Bundle 3 — "Fix the empty pages"

- ⬜ **#11 Discipline tiles**
  - `app/Http/Controllers/DisciplineController.php` (index) — order disciplines by upcoming-match count desc, then name
  - Tiles with 0 upcoming: show "Next match: not yet listed — follow this discipline" with a follow button (already exists as `<livewire:follow-button>`)
  - Or collapse all-zero tiles behind an "All 31 disciplines" accordion — recommend showing all but re-sorting
- ⬜ **#12 Industry directory empty**
  - Two options:
    - **Hide until seeded:** wrap the homepage Industry section + top-nav link + hero stat in `@if (\App\Models\Provider::published()->exists())`
    - **Seed pre-launch:** add 10–15 real providers we can list free-tier before we start selling. Recommend hiding until you have at least 5 real listings — an empty directory is a worse ad for the ad product than no directory at all.
- ⬜ **#13 Ad block below first row of results**
  - `resources/views/public/calendar.blade.php:15` — move `<x-ad-slot>` from top-of-wrap to after the first grid row
  - Same for `resources/views/public/ranges/index.blade.php:12` and `resources/views/public/suppliers/index.blade.php:12`
  - Add `hide-when-vacant` while we're there so unsold slots don't show the house pitch on empty inventory

**Acceptance:** the Discover grid leads with disciplines that have matches; Industry either has 5+ real providers or does not exist in the nav; `/calendar` doesn't show an ad above its filters.

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
