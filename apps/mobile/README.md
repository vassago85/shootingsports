# Shooting Sports mobile app

Expo (SDK 57) + Expo Router + TypeScript. The design brief and screen
spec live in `docs/build-pack/BUILD_SPEC.md`. The design tokens live
in `docs/build-pack/design/tokens.json` and are mirrored into
`src/theme.ts`.

## Why we pin to SDK 57 (and not the latest Expo)

Section 0.2 of `BUILD_SPEC.md` says: *"Target the latest stable Expo
SDK, Expo Router and React Native. Never pin to an older major to
make code fit. If the repo is behind, flag it and keep the upgrade
as a separate change."*

We're following the flag-it clause. The reason is downstream, not us:

- The South African Google Play Store serves **Expo Go 2.33.x**,
  which bundles SDK 57 as its newest supported SDK.
- Expo Go on US / EU stores already has SDK 58+. SA has not caught
  up at the time of writing (2026-10-21).
- Testing on a real device via Expo Go is the fastest feedback loop
  we have during Phase 1. Jumping to SDK 58 would force every
  tester onto a custom dev-client APK before they can open the app.

### When to lift the pin

Lift the pin to the latest SDK when **either**:

1. The SA Play Store ships an Expo Go that bundles the newer SDK; or
2. We've shipped a custom dev-client APK through EAS Build and
   handed it to all testers.

Whichever happens first, update:

- `apps/mobile/package.json` dependency pins — `expo`, every
  `expo-*` package, `react`, `react-native`, `react-native-*`.
- `app.json` plugins list — some plugins (Notifications, Maps)
  gain new properties across SDKs.
- Re-run `npx expo-doctor` and fix every warning before shipping.

## Local development

```bash
cd apps/mobile
npm install
npm start      # Expo CLI, prints a QR for Expo Go
npm run android
npm run ios
npm run web
npm run typecheck
```

The dev server needs the Laravel API reachable from your phone.
Point `EXPO_PUBLIC_API_URL` in `apps/mobile/.env` at your LAN IP
(e.g. `http://192.168.1.124:8000`) and run `php artisan serve
--host=0.0.0.0` so the Samsung on Wi-Fi can hit it.
