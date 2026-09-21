# Native apps roadmap — shooters-first

**Date:** 2026-09-17
**Status:** Decision locked. Phase 0 PWA and Phase 1 API are in scope
for immediate implementation; Phase 2 (Expo scaffold) starts once the
API is green in Pest and a device build exists.

## Goal

Give shooters an app-shaped experience for discovery — calendar, map,
match detail, saved events, push — without prematurely shipping to the
App Store while the [co-founder briefing §16 gates](../../../CLAUDE.md)
are still open. Web MVP stays canonical; a native client consumes the
same domain via a versioned JSON API.

## Decisions

| Decision | Choice | Why |
|---|---|---|
| Audience | Shooters | Discovery & retention live here; MDs already have Filament desk |
| Path | PWA → API → Expo | Cheapest engagement lever first, then thin foundation, then thin client |
| Backend | Laravel **Sanctum** personal access tokens (Bearer) | First-party, no OAuth server complexity, session `web` stays for Blade/Livewire |
| Client | **Expo (React Native)** | One codebase iOS/Android, EAS Build, push via Expo Notifications → FCM/APNs |
| API prefix | `/api/v1` | Explicit versioning from day one; second version ships without breaking clients |
| Push transport | Expo push tokens (stored per user, per device) | Deliverable through Expo's service to APNs + FCM without app-side SDK swaps |
| Not chosen | Capacitor wrap of Livewire | Poor offline / auth story, wraps Blade responses that already work in mobile Safari |
| Not chosen | Flutter | Second language stack for a Laravel-first team |
| Not chosen | Native SwiftUI + Compose | Doubles the surface area for a two-screen MVP |

## Phase 0 — PWA / web engagement

Ships on the existing site. Nothing store-related.

- Web app manifest + service-worker shell cache for `/calendar`, `/map`,
  and match detail pages.
- No Web Push in this phase — iOS Safari support is uneven and existing
  `EmailPreferences` already covers match alerts and digests.
- Continue closing the mobile web UX backlog from
  [ux-audit-fixes](2026-09-16-ux-audit-fixes.md) (filter sheet, etc.).
  Native must not become the fix for filter-UX debt.

**Gate to leave Phase 0:** organic mobile traffic measurable in Umami
for 30 days AND ≥ 5% of authenticated shooters have used
`/my-calendar` / saved-events on mobile.

## Phase 1 — Versioned public API + auth

Thin JSON layer that reuses the existing domain (`PublicEventQuery`,
`Event`, `Discipline`, `Venue`, `Organisation`, `User::savedEvents()`).
No Livewire in the request path.

### Auth surface

- `POST /api/v1/auth/login` — email + password + optional `device_name`;
  returns `{ token, user }`.
- `POST /api/v1/auth/logout` — revokes the current token.
- `GET /api/v1/me` — auth required; returns the profile shape needed by
  the mobile UI (name, plan, calendar slug, notification prefs).

Guests may hit any public endpoint below without a token; auth-only
endpoints require `Authorization: Bearer <token>` with the `sanctum`
guard.

### Discovery endpoints (public)

- `GET /api/v1/events` — filters mirror
  [`PublicEventQuery::fromRequest()`](../../../app/Queries/PublicEventQuery.php)
  (`family`, `discipline`, `province`, `radius`, `near`, `lat`, `lng`,
  `from`, `to`, `novice`, `confirmed`).
- `GET /api/v1/events/{slug}` — full match detail.
- `GET /api/v1/disciplines` — top-level tree (name + family + slug +
  upcoming counts).
- `GET /api/v1/map` — province + venue-pin payload that mirrors the
  cached shape used by [`MapController`](../../../app/Http/Controllers/MapController.php).

### Shooter endpoints (auth required)

- `GET /api/v1/me/saved-events` — the current user's `savedEvents()`.
- `POST /api/v1/me/saved-events` — `{ event_id }`, idempotent.
- `DELETE /api/v1/me/saved-events/{event}` — remove one.
- `POST /api/v1/me/device-tokens` — register an Expo push token
  (`{ token, platform }`).
- `DELETE /api/v1/me/device-tokens` — deregister the current device.

### Implementation notes

- Eloquent API Resources per model — no Blade fields leak.
- Rate limit `auth.*` (5/min per IP) and `me.*` write routes
  (60/min per user).
- Feature tests with Pest for filter passthrough, resource shape, auth
  boundaries, and idempotent save/unsave.
- Do **not** expose Filament desk/admin models via this API in v1.
- CORS: default deny; permit `EXPO_PUBLIC_API_URL` origins only if a
  web build lands. Native apps use Bearer tokens on the same origin as
  the API, no CORS involved.

**Gate to leave Phase 1:** Pest suite green + a smoke script (curl or a
one-page Expo fetch) proves list + show + login + save round-trip.

## Phase 2 — Expo thin discovery MVP

### Screens

1. Calendar list (filters = API query params)
2. Map (province clusters → filtered calendar)
3. Match detail (entry URL opens the system browser)
4. Auth + My calendar (saved events)
5. Push opt-in + deep link to match

### Out of scope for the first client

- Attendance log
- Pro / Paystack upgrade
- Match Director desk
- Supplier directory
- Livewire save-search UI

### Repo layout

- `apps/mobile/` sibling to the Laravel app, checked into this repo.
- Expo Router; env `EXPO_PUBLIC_API_URL`.
- Token storage via `expo-secure-store`.
- Push via `expo-notifications` → Expo push service → APNs/FCM.

**Gate to leave Phase 2:** TestFlight + Play internal testing with
5–10 shooters; push delivery verified on ≥ 1 iOS + 1 Android device.

## Phase 3 — Push productization

- Wire saved-search / followed-discipline / nearby-event jobs to Expo
  push using the `device_tokens` table. Today match-alert prefs are
  UI-only.
- Respect existing [`EmailPreferences`](../../../app/Support/EmailPreferences.php)
  / notification categories; add push channel flags rather than
  parallel preferences.
- Document Samsung "sleeping apps" and iOS permission realities in
  support copy.

## What this spec explicitly does not cover

- App Store / Play Store submission mechanics.
- Server-side Firebase Admin credentials — Phase 3 will pick Expo's
  push service vs a direct FCM integration.
- Deep-link universal-links / associated domains setup (comes with
  Phase 2 device builds).

## Appendix — App Store readiness

App Store / Play submission mechanics stay out of the phased build
above, but the **preparation** for submission is no longer unowned.
See [2026-09-21-app-store-readiness-audit.md](2026-09-21-app-store-readiness-audit.md)
for the requirement matrix and the prioritised backlog (in-app
account deletion, expanded POPIA notice, public password reset,
reviewer demo account, review notes, universal links, store metadata).
