import { Ionicons } from '@expo/vector-icons';
import { Tabs } from 'expo-router';

import { colors, fonts } from '@/theme';

/**
 * Four tabs, always, for every signed-in or guest shooter. No tab
 * appears or disappears based on role — the Manage hub lives inside
 * the Me tab and only shows up when the shooter also wears a club,
 * shop, range, or media hat.
 *
 * Spec §3.1: "The tab bar is forest. Inactive items are control-
 * coloured, the active item is lime, and each tab has an icon over
 * its label." Icons use the outline variant when inactive and the
 * solid variant when active.
 */
export default function TabsLayout() {
  return (
    <Tabs
      screenOptions={{
        tabBarActiveTintColor: colors.lime,
        tabBarInactiveTintColor: colors.control,
        tabBarStyle: { backgroundColor: colors.forest, borderTopColor: colors.forest },
        headerStyle: { backgroundColor: colors.forest },
        headerTintColor: colors.onForest,
        headerTitleStyle: { fontFamily: fonts.display, fontSize: 18 },
        sceneStyle: { backgroundColor: colors.page },
      }}
    >
      <Tabs.Screen
        name="index"
        options={{
          title: 'Home',
          // M2 draws its own HeroBand that extends under the status bar
          // (spec §3.1: "Do not draw a fake status bar"), so the stock
          // tab header is suppressed here. Other tabs still use it
          // until each one gets its own HeroBand reskin.
          headerShown: false,
          tabBarIcon: ({ color, focused, size }) => (
            // RN 0.88 widened `color` to include ProcessedColorValue (number).
            // Expo Router's tab bar hands us string tokens from the theme, so a
            // narrowing cast is safe and keeps Ionicons' typed `color` prop happy.
            <Ionicons name={focused ? 'home' : 'home-outline'} size={size} color={color as string} />
          ),
        }}
      />
      <Tabs.Screen
        name="calendar"
        options={{
          title: 'Calendar',
          // M3 draws its own HeroBand + sticky filter strip; suppress
          // the stock tab header so the HeroBand sits under the status
          // bar as spec §3.1 requires.
          headerShown: false,
          tabBarIcon: ({ color, focused, size }) => (
            <Ionicons name={focused ? 'calendar' : 'calendar-outline'} size={size} color={color as string} />
          ),
        }}
      />
      <Tabs.Screen
        name="find"
        options={{
          title: 'Find',
          // M4 draws its own HeroBand + chip strip; suppress the stock
          // tab header so the HeroBand extends under the status bar per
          // spec §3.1.
          headerShown: false,
          tabBarIcon: ({ color, focused, size }) => (
            <Ionicons name={focused ? 'search' : 'search-outline'} size={size} color={color as string} />
          ),
        }}
      />
      <Tabs.Screen
        name="me"
        options={{
          title: 'Me',
          // M5 draws its own HeroBand (and signed-out state owns its
          // own full-screen layout). Suppress the stock header here.
          headerShown: false,
          tabBarIcon: ({ color, focused, size }) => (
            <Ionicons name={focused ? 'person' : 'person-outline'} size={size} color={color as string} />
          ),
        }}
      />
    </Tabs>
  );
}
