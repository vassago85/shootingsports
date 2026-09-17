import { Link, useRouter } from 'expo-router';
import { useCallback, useEffect, useState } from 'react';
import { ActivityIndicator, FlatList, StyleSheet, Text, TouchableOpacity, View } from 'react-native';

import { EventCard } from '@/api/events';
import { listSavedEvents, unsaveEvent } from '@/api/saved-events';
import { logout } from '@/api/auth';
import { getAuthToken } from '@/auth/store';

export default function SavedScreen() {
  const router = useRouter();
  const [signedIn, setSignedIn] = useState<boolean | null>(null);
  const [events, setEvents] = useState<EventCard[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

  const load = useCallback(async () => {
    setError(null);
    try {
      const rows = await listSavedEvents();
      setEvents(rows);
    } catch (err) {
      setError(err instanceof Error ? err.message : 'Could not load your saved matches.');
    } finally {
      setLoading(false);
    }
  }, []);

  useEffect(() => {
    void (async () => {
      const token = await getAuthToken();
      const hasToken = token !== null;
      setSignedIn(hasToken);

      if (hasToken) {
        await load();
      } else {
        setLoading(false);
      }
    })();
  }, [load]);

  if (signedIn === false) {
    return (
      <View style={styles.center}>
        <Text style={styles.copy}>Sign in to pin matches to your calendar.</Text>
        <TouchableOpacity onPress={() => router.push('/login')} style={styles.cta}>
          <Text style={styles.ctaText}>Log in</Text>
        </TouchableOpacity>
      </View>
    );
  }

  if (loading) {
    return (
      <View style={styles.center}>
        <ActivityIndicator />
      </View>
    );
  }

  return (
    <View style={styles.container}>
      {error !== null && <Text style={styles.error}>{error}</Text>}

      <FlatList
        data={events}
        keyExtractor={(item) => String(item.id)}
        contentContainerStyle={{ padding: 12, gap: 12 }}
        ListEmptyComponent={<Text style={styles.empty}>Nothing saved yet.</Text>}
        renderItem={({ item }) => (
          <View style={styles.card}>
            <Link
              href={{ pathname: '/matches/[slug]', params: { slug: item.slug } }}
              style={{ flex: 1 }}
            >
              <Text style={styles.title}>{item.title}</Text>
              <Text style={styles.meta}>
                {item.starts_at !== null ? new Date(item.starts_at).toDateString() : 'TBC'} · {item.location_label}
              </Text>
            </Link>
            <TouchableOpacity
              onPress={async () => {
                await unsaveEvent(item.id);
                await load();
              }}
              style={styles.remove}
            >
              <Text style={styles.removeText}>Remove</Text>
            </TouchableOpacity>
          </View>
        )}
      />

      <TouchableOpacity
        onPress={async () => {
          await logout();
          setSignedIn(false);
        }}
        style={styles.logoutRow}
      >
        <Text style={styles.logoutText}>Log out</Text>
      </TouchableOpacity>
    </View>
  );
}

const styles = StyleSheet.create({
  container: { flex: 1, backgroundColor: '#101516' },
  center: { flex: 1, alignItems: 'center', justifyContent: 'center', backgroundColor: '#101516', gap: 12 },
  copy: { color: '#f4f0e6', fontSize: 16, textAlign: 'center', paddingHorizontal: 24 },
  cta: { backgroundColor: '#d9ae52', paddingHorizontal: 20, paddingVertical: 10, borderRadius: 6 },
  ctaText: { color: '#232a2c', fontWeight: '700' },
  card: {
    backgroundColor: '#1c2426',
    borderRadius: 8,
    padding: 14,
    flexDirection: 'row',
    alignItems: 'center',
    gap: 12,
  },
  title: { color: '#f4f0e6', fontSize: 15, fontWeight: '600' },
  meta: { color: '#a3adaa', fontSize: 12, marginTop: 4 },
  remove: { paddingHorizontal: 10, paddingVertical: 6, borderRadius: 4, borderWidth: 1, borderColor: '#3a4548' },
  removeText: { color: '#f4f0e6', fontSize: 12 },
  empty: { color: '#a3adaa', textAlign: 'center', marginTop: 48 },
  error: { color: '#f28c8c', margin: 12, textAlign: 'center' },
  logoutRow: { padding: 16, alignItems: 'center' },
  logoutText: { color: '#a3adaa', fontSize: 13 },
});
