# Shooting Sports — mobile client

Expo Router app that consumes the Laravel `/api/v1` surface for the
shooters-first discovery MVP. See
`docs/superpowers/specs/2026-09-17-native-apps-roadmap.md` for the
phased plan this scaffold satisfies.

## Prerequisites

- Node 20+
- Expo CLI (`npx expo` — no global install required)
- iOS Simulator (Xcode) or Android emulator, or Expo Go on a device
- Laravel API running locally at `http://127.0.0.1:8000` (or accessible
  from the device via LAN IP / `10.0.2.2` on Android emulator)

## Setup

```bash
cd apps/mobile
cp .env.example .env         # then edit EXPO_PUBLIC_API_URL
npm install
npx expo install --check     # aligns package versions with the SDK
```

## Run

```bash
npx expo start
```

Then press `i` for iOS Simulator, `a` for Android emulator, or scan
the QR code with Expo Go.

## Environment

| Variable | Purpose |
|---|---|
| `EXPO_PUBLIC_API_URL` | Base URL of the Laravel API. Android emulator points at `10.0.2.2:8000` for host `127.0.0.1`. iOS Simulator can use `http://127.0.0.1:8000`. Real devices need the host machine's LAN IP. |

## Screens

Four tabs, always, for every signed-in or guest shooter. No tab
appears or disappears based on role — the Manage hub lives inside
the Me tab and only shows up when the shooter also wears a club,
shop, range, or media hat.

- `/` Home — next upcoming matches on the national calendar
- `/calendar` Calendar list with family + novice filters
- `/find` Discovery surface with chips for Events / Clubs / Ranges /
  Shops / Sports. Events is backed by the province cluster map today;
  the other chips light up as their directory endpoints land.
- `/me` Shooter home. Plan state, saved matches, and (if applicable)
  the Manage hub. Heavy desk work (members, invoices, magazine,
  billing) opens the website via the system browser rather than
  rendering inline, so Apple / Google in-app purchase rules do not
  apply. Covers eight visual states (M5a–M5h in
  `docs/superpowers/specs/2026-10-20-app-and-web-mockup-framework.md`):
  signed-out, Free + no caps, Free + 1 cap, Pro, Trial, all four
  caps, loading, and error.
- `/matches/[slug]` Match detail
- `/login` Email + password login

## Push

`src/push/register.ts` runs on app launch when the user is signed in
and stores an Expo push token via `POST /api/v1/me/device-tokens`.
Delivery is wired server-side in Phase 3.

## Before EAS builds

- Add app icon + notification icon + adaptive icon PNGs under `./assets/`
  and reference them in `app.json` (currently omitted so a fresh clone
  boots without the assets present).
- Set `extra.eas.projectId` in `app.json` after running `eas init`.
- Set `android.config.googleMaps.apiKey` for release builds. Dev builds
  via Expo Go work without a key.
