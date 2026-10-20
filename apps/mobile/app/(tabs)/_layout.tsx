import { Tabs } from 'expo-router';

/**
 * Four tabs, always, for every signed-in or guest shooter. No tab
 * appears or disappears based on role — the Manage hub lives inside
 * the Me tab and only shows up when the shooter also wears a club,
 * shop, range, or media hat.
 */
export default function TabsLayout() {
  return (
    <Tabs
      screenOptions={{
        tabBarActiveTintColor: '#d9ae52',
        headerStyle: { backgroundColor: '#232a2c' },
        headerTintColor: '#f4f0e6',
        headerTitleStyle: { fontWeight: '600' },
      }}
    >
      <Tabs.Screen name="index" options={{ title: 'Home' }} />
      <Tabs.Screen name="calendar" options={{ title: 'Calendar' }} />
      <Tabs.Screen name="find" options={{ title: 'Find' }} />
      <Tabs.Screen name="me" options={{ title: 'Me' }} />
    </Tabs>
  );
}
