import { Link, useRouter } from 'expo-router';
import { useCallback, useEffect, useState } from 'react';
import { ActivityIndicator, RefreshControl, ScrollView, StyleSheet, Text, TouchableOpacity, View } from 'react-native';

import { EventCard, listEvents } from '@/api/events';

/**
 * Home — the reason to open the app on a Friday. The next few
 * upcoming matches on the national calendar; following and
 * saved-event personalisation plugs in once those endpoints exist
 * (follows + saved-searches live on the website today, not the API).
 */
export default function HomeScreen() {
  const router = useRouter();
  const [events, setEvents] = useState<EventCard[]>([]);
  const [loading, setLoading] = useState(true);
  const [refreshing, setRefreshing] = useState(false);
  const [error, setError] = useState<string | null>(null);

  const load = useCallback(async () => {
    setError(null);
    try {
      const { events: rows } = await listEvents({ family: null, novice: false, province: null });
      setEvents(rows.slice(0, 6));
    } catch (err) {
      setError(err instanceof Error ? err.message : 'Could not load matches.');
    } finally {
      setLoading(false);
      setRefreshing(false);
    }
  }, []);

  useEffect(() => {
    void load();
  }, [load]);

  return (
    <ScrollView
      style={styles.container}
      contentContainerStyle={{ padding: 16, gap: 20 }}
      refreshControl={
        <RefreshControl
          refreshing={refreshing}
          onRefresh={() => {
            setRefreshing(true);
            void load();
          }}
        />
      }
    >
      <View>
        <Text style={styles.eyebrow}>This weekend</Text>
        <Text style={styles.h1}>What is on</Text>
      </View>

      {loading ? (
        <ActivityIndicator />
      ) : error !== null ? (
        <Text style={styles.error}>{error}</Text>
      ) : events.length === 0 ? (
        <Text style={styles.muted}>No upcoming matches yet. Pull to refresh.</Text>
      ) : (
        <View style={{ gap: 10 }}>
          {events.map((event) => (
            <Link
              key={event.id}
              href={{ pathname: '/matches/[slug]', params: { slug: event.slug } }}
              asChild
            >
              <TouchableOpacity style={styles.card}>
                <Text style={styles.cardTitle}>{event.title}</Text>
                <Text style={styles.cardMeta}>
                  {event.starts_at !== null ? new Date(event.starts_at).toDateString() : 'TBC'}
                </Text>
                <Text style={styles.cardMeta}>{event.location_label}</Text>
              </TouchableOpacity>
            </Link>
          ))}
        </View>
      )}

      <TouchableOpacity style={styles.cta} onPress={() => router.push('/calendar')}>
        <Text style={styles.ctaText}>Browse the full calendar</Text>
      </TouchableOpacity>
    </ScrollView>
  );
}

const styles = StyleSheet.create({
  container: { flex: 1, backgroundColor: '#101516' },
  eyebrow: { color: '#d9ae52', fontSize: 12, letterSpacing: 1.1, textTransform: 'uppercase' },
  h1: { color: '#f4f0e6', fontSize: 24, fontWeight: '700', marginTop: 4 },
  muted: { color: '#a3adaa', fontSize: 14 },
  error: { color: '#f28c8c', fontSize: 14 },
  card: {
    backgroundColor: '#1c2426',
    borderRadius: 8,
    padding: 14,
    gap: 4,
  },
  cardTitle: { color: '#f4f0e6', fontSize: 16, fontWeight: '600' },
  cardMeta: { color: '#a3adaa', fontSize: 12 },
  cta: {
    backgroundColor: '#d9ae52',
    paddingVertical: 12,
    borderRadius: 6,
    alignItems: 'center',
  },
  ctaText: { color: '#232a2c', fontWeight: '700' },
});
