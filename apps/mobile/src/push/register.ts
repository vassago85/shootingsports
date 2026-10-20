import Constants from 'expo-constants';
import { Platform } from 'react-native';

import { registerDeviceToken } from '@/api/device-tokens';

type PushPlatform = 'ios' | 'android' | 'web';

/**
 * The API's device_tokens table only recognises ios / android / web.
 * Any desktop target (windows / macos) that Expo may add in the
 * future maps to 'web' so we never crash the launch path over an
 * unexpected platform value. The exhaustive-switch pattern isn't
 * worth it here — new Platform.OS values should fall back safely,
 * not refuse to compile.
 */
function resolvePlatform(): PushPlatform {
  switch (Platform.OS) {
    case 'ios':
      return 'ios';
    case 'android':
      return 'android';
    default:
      return 'web';
  }
}

/**
 * True when the app is running inside Expo Go (the Store Client).
 * Expo Go has not shipped the push runtime on Android since SDK 53,
 * and the native `ExpoTopicSubscriptionModule` is missing on both
 * platforms there — importing expo-notifications at module scope is
 * enough to crash the launch screen. For store-client runs we skip
 * the whole push path. Development builds and production binaries
 * (built via EAS) run the real registration below.
 *
 * `executionEnvironment` is the modern API; `appOwnership` is the
 * legacy fallback. Checking both means a future Expo change to one
 * does not silently break the guard.
 */
function isExpoGo(): boolean {
  const env = Constants.executionEnvironment;
  const ownership = Constants.appOwnership;

  return env === 'storeClient' || ownership === 'expo';
}

/**
 * Requests notification permission and, if granted, registers the
 * Expo push token with the Laravel API. Safe to call multiple times —
 * the API `POST /me/device-tokens` endpoint is idempotent.
 *
 * Returns the token string on success, `null` on Expo Go, permission
 * denial, or when running in a simulator without a project id.
 */
export async function registerForPushNotifications(): Promise<string | null> {
  if (isExpoGo()) {
    return null;
  }

  // Lazy-load expo-notifications so its native-module side-effects
  // (setNotificationHandler, the ExpoTopicSubscriptionModule probe)
  // never run on module eval in Expo Go. If isExpoGo() missed a new
  // runtime, the import itself still would not fire.
  const Notifications = await import('expo-notifications');

  Notifications.setNotificationHandler({
    handleNotification: async () => ({
      shouldShowBanner: true,
      shouldShowList: true,
      shouldPlaySound: true,
      shouldSetBadge: false,
    }),
  });

  if (Platform.OS === 'android') {
    await Notifications.setNotificationChannelAsync('default', {
      name: 'default',
      importance: Notifications.AndroidImportance.DEFAULT,
    });
  }

  const existing = await Notifications.getPermissionsAsync();
  let status = existing.status;

  if (status !== 'granted') {
    const request = await Notifications.requestPermissionsAsync();
    status = request.status;
  }

  if (status !== 'granted') {
    return null;
  }

  const projectId =
    Constants.expoConfig?.extra?.eas?.projectId ??
    Constants.easConfig?.projectId;

  if (typeof projectId !== 'string' || projectId.length === 0) {
    // No EAS project id yet — skip silently so simulators and dev
    // clients without a build profile don't crash. Registration
    // will succeed once the app is provisioned in EAS.
    return null;
  }

  const tokenResponse = await Notifications.getExpoPushTokenAsync({ projectId });

  await registerDeviceToken(tokenResponse.data, resolvePlatform());

  return tokenResponse.data;
}
