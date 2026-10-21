import {
  IBMPlexMono_400Regular,
  IBMPlexMono_600SemiBold,
} from '@expo-google-fonts/ibm-plex-mono';
import {
  IBMPlexSans_400Regular,
  IBMPlexSans_500Medium,
  IBMPlexSans_600SemiBold,
} from '@expo-google-fonts/ibm-plex-sans';
import {
  SairaCondensed_600SemiBold,
  SairaCondensed_700Bold,
} from '@expo-google-fonts/saira-condensed';
import { useFonts } from 'expo-font';
import { Stack } from 'expo-router';
import * as SplashScreen from 'expo-splash-screen';
import { StatusBar } from 'expo-status-bar';
import { useEffect, useState } from 'react';
import { ActivityIndicator, View } from 'react-native';

import { getStoredUser } from '@/auth/store';
import { registerForPushNotifications } from '@/push/register';
import { colors, fonts } from '@/theme';

// Keep the native splash on screen until fonts AND the auth check
// are done. Without this the app flashes a fallback font for ~400ms
// on first launch and then jumps to the real display face.
SplashScreen.preventAutoHideAsync().catch(() => {
  // No-op: calling this twice can throw; the splash is already held.
});

/**
 * Root layout.
 *
 * Two concurrent startup tasks must both finish before the first
 * screen renders:
 *
 *   1. `useFonts` — Saira Condensed (display), IBM Plex Sans (body),
 *      IBM Plex Mono (eyebrow/meta). The spec treats these as
 *      load-bearing; everything else references them through the
 *      `fonts.*` tokens in `src/theme.ts`.
 *   2. Auth state restore — read the stored token so we know whether
 *      to fire the (best-effort) push-notification registration flow.
 *
 * We never block the splash on push registration itself — that's
 * fire-and-forget, and the fallbacks in `push/register.ts` already
 * swallow Expo Go limitations.
 *
 * The nav shape here is deliberately minimal for Phase 1 bootstrap;
 * the proper Stack-in-Tabs restructure per BUILD_SPEC §3.1 is the
 * next Phase 1 todo (p1_nav).
 */
export default function RootLayout() {
  const [fontsLoaded] = useFonts({
    SairaCondensed_600SemiBold,
    SairaCondensed_700Bold,
    IBMPlexSans_400Regular,
    IBMPlexSans_500Medium,
    IBMPlexSans_600SemiBold,
    IBMPlexMono_400Regular,
    IBMPlexMono_600SemiBold,
  });
  const [authReady, setAuthReady] = useState(false);

  useEffect(() => {
    void (async () => {
      const stored = await getStoredUser();

      if (stored !== null) {
        // Best-effort push registration — never blocks the splash.
        // Errors are swallowed; real failures surface in the Me tab.
        void registerForPushNotifications().catch(() => null);
      }

      setAuthReady(true);
    })();
  }, []);

  const ready = fontsLoaded && authReady;

  useEffect(() => {
    if (ready) {
      void SplashScreen.hideAsync();
    }
  }, [ready]);

  // Hard-blocking spinner. In practice the splash screen is still
  // visible here on first launch, so the user sees the splash, not
  // this. On a warm resume the splash is gone and this fills the gap.
  if (!ready) {
    return (
      <View
        style={{
          flex: 1,
          alignItems: 'center',
          justifyContent: 'center',
          backgroundColor: colors.page,
        }}
      >
        <ActivityIndicator color={colors.link} />
      </View>
    );
  }

  return (
    <>
      {/* Light status bar icons on the forest hero band. */}
      <StatusBar style="light" />
      <Stack
        screenOptions={{
          headerStyle: { backgroundColor: colors.forest },
          headerTintColor: colors.onForest,
          headerTitleStyle: { fontFamily: fonts.display, fontSize: 18 },
          contentStyle: { backgroundColor: colors.page },
        }}
      >
        <Stack.Screen name="(tabs)" options={{ headerShown: false }} />
        <Stack.Screen name="login" options={{ title: 'Sign in' }} />
        <Stack.Screen name="matches/[slug]" options={{ title: 'Match' }} />
      </Stack>
    </>
  );
}
