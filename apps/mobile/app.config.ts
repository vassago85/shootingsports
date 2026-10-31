import type { ConfigContext, ExpoConfig } from 'expo/config';

/**
 * Dynamic app config. Everything static lives in `app.json`; this file
 * only overlays values that must come from the build environment
 * (secrets, per-channel toggles) so the committed JSON stays
 * reviewable and secret-free.
 *
 * The Google Maps Android key is a **restricted** key (restricted to
 * `com.shootingsports.app` + the EAS Android signing SHA-1). It must
 * be provided via the `GOOGLE_MAPS_ANDROID_API_KEY` env var at build
 * time:
 *   - Local dev with Google Maps: add it to `apps/mobile/.env` (gitignored)
 *   - EAS builds:  `eas secret:create --scope project \
 *                      --name GOOGLE_MAPS_ANDROID_API_KEY --value <key>`
 *
 * If the var is unset the Android build still succeeds; the map tiles
 * just won't load, which is loud enough to catch in testing.
 */
export default ({ config }: ConfigContext): ExpoConfig => {
  const googleMapsApiKey = process.env.GOOGLE_MAPS_ANDROID_API_KEY;

  return {
    ...config,
    name: config.name ?? 'Shooting Sports',
    slug: config.slug ?? 'shootingsports',
    android: {
      ...config.android,
      ...(googleMapsApiKey !== undefined && googleMapsApiKey.length > 0
        ? { config: { googleMaps: { apiKey: googleMapsApiKey } } }
        : {}),
    },
  };
};
