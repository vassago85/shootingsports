import { Stack } from 'expo-router';
import { StatusBar } from 'expo-status-bar';
import { useEffect, useState } from 'react';
import { ActivityIndicator, View } from 'react-native';

import { getStoredUser, StoredUser } from '@/auth/store';
import { registerForPushNotifications } from '@/push/register';

export default function RootLayout() {
  const [ready, setReady] = useState(false);
  const [_user, setUser] = useState<StoredUser | null>(null);

  useEffect(() => {
    void (async () => {
      const stored = await getStoredUser();
      setUser(stored);

      if (stored !== null) {
        // Fire and forget — push registration errors should not block
        // the launch screen. Real errors surface in the settings screen.
        void registerForPushNotifications().catch(() => null);
      }

      setReady(true);
    })();
  }, []);

  if (!ready) {
    return (
      <View style={{ flex: 1, alignItems: 'center', justifyContent: 'center' }}>
        <ActivityIndicator />
      </View>
    );
  }

  return (
    <>
      <StatusBar style="auto" />
      <Stack
        screenOptions={{
          headerStyle: { backgroundColor: '#232a2c' },
          headerTintColor: '#f4f0e6',
          headerTitleStyle: { fontWeight: '600' },
        }}
      >
        <Stack.Screen name="(tabs)" options={{ headerShown: false }} />
        <Stack.Screen name="login" options={{ title: 'Log in' }} />
        <Stack.Screen name="matches/[slug]" options={{ title: 'Match' }} />
      </Stack>
    </>
  );
}
