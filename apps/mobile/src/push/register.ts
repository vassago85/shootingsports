import Constants from 'expo-constants';
import * as Notifications from 'expo-notifications';
import { Platform } from 'react-native';

import { registerDeviceToken } from '@/api/device-tokens';

Notifications.setNotificationHandler({
  handleNotification: async () => ({
    shouldShowAlert: true,
    shouldPlaySound: true,
    shouldSetBadge: false,
  }),
});

type PushPlatform = 'ios' | 'android' | 'web';

function resolvePlatform(): PushPlatform {
  switch (Platform.OS) {
    case 'ios':
      return 'ios';
    case 'android':
      return 'android';
    case 'web':
      return 'web';
    case 'windows':
    case 'macos':
      return 'web';
    default: {
      const _exhaustive: never = Platform.OS;
      return _exhaustive;
    }
  }
}

/**
 * Requests notification permission and, if granted, registers the
 * Expo push token with the Laravel API. Safe to call multiple times —
 * the API `POST /me/device-tokens` endpoint is idempotent.
 *
 * Returns the token string on success, `null` on permission denial or
 * when running in a simulator without a project id.
 */
export async function registerForPushNotifications(): Promise<string | null> {
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
    // No EAS project id yet — skip silently so simulators without a
    // build profile don't crash. Registration will succeed once the
    // app is provisioned in EAS.
    return null;
  }

  const tokenResponse = await Notifications.getExpoPushTokenAsync({ projectId });

  await registerDeviceToken(tokenResponse.data, resolvePlatform());

  return tokenResponse.data;
}
