# UX Audit Bundle A — Credibility (16 Sep 2026)

Approved approach: hide Industry until seeded; match page layout-only.

## Scope

1. **Industry** — Keep nav/footer/home gated on `Provider::isDirectoryPopulated()`. Soften `/advertise` supplier pitch. `/suppliers` empty states → "Free to list — claim this category".
2. **Disciplines** — Show populated tiles first; "Show all N disciplines →" toggle for empties. Empty discipline page CTA to follow.
3. **Verification** — Drop "Unconfirmed" badge; keep positive Confirmed/Ageing only.
4. **Stat bar** — Matches · Ranges · Disciplines · Clubs & series. Drop Provinces/Industry.
5. **Map** — CARTO Positron tiles; empty-province copy + claim link; bbox-guard centroids.
6. **Match page** — Two-column facts-first; linked venue + Directions; one Enter here primary; named host/range text links. No new DB fields.

## Out of scope (Bundle B)

Calendar finder collapse, hero scroll, mobile filters, header search, card equal-height, clubs/ranges grid, contrast, tap targets, banners, 404 header.
