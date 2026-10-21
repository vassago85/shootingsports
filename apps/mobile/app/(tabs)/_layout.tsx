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
          tabBarIcon: ({ color, focused, size }) => (
            <Ionicons name={focused ? 'home' : 'home-outline'} size={size} color={color} />
          ),
        }}
      />
      <Tabs.Screen
        name="calendar"
        options={{
          title: 'Calendar',
          tabBarIcon: ({ color, focused, size }) => (
            <Ionicons name={focused ? 'calendar' : 'calendar-outline'} size={size} color={color} />
          ),
        }}
      />
      <Tabs.Screen
        name="find"
        options={{
          title: 'Find',
          tabBarIcon: ({ color, focused, size }) => (
            <Ionicons name={focused ? 'search' : 'search-outline'} size={size} color={color} />
          ),
        }}
      />
      <Tabs.Screen
        name="me"
        options={{
          title: 'Me',
          tabBarIcon: ({ color, focused, size }) => (
            <Ionicons name={focused ? 'person' : 'person-outline'} size={size} color={color} />
          ),
        }}
      />
    </Tabs>
  );
}
