import { Ionicons } from '@expo/vector-icons';
import { Tabs } from 'expo-router';

/**
 * Four tabs, always, for every signed-in or guest shooter. No tab
 * appears or disappears based on role — the Manage hub lives inside
 * the Me tab and only shows up when the shooter also wears a club,
 * shop, range, or media hat.
 *
 * Icons use the outline variant when inactive and the solid variant
 * when active, matching how the tokens doc describes "active chip
 * flipped colours" for the brass accent.
 */
export default function TabsLayout() {
  return (
    <Tabs
      screenOptions={{
        tabBarActiveTintColor: '#d9ae52',
        tabBarInactiveTintColor: '#a3adaa',
        tabBarStyle: { backgroundColor: '#161c1e', borderTopColor: '#3a4548' },
        headerStyle: { backgroundColor: '#232a2c' },
        headerTintColor: '#f4f0e6',
        headerTitleStyle: { fontWeight: '600' },
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
