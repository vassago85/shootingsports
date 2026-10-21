import { Link, useRouter } from 'expo-router';
import { useCallback, useEffect, useState } from 'react';
import { ActivityIndicator, RefreshControl, ScrollView, StyleSheet, Text, TouchableOpacity, View } from 'react-native';

import { EventCard, listEvents } from '@/api/events';
import { colors, fonts } from '@/theme';

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
        <ActivityIndicator color={colors.link} />
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

// TEMPORARY stylesheet — stays until the full M2 reskin lands
// (HeroBand + MatchCard + GhostButton primitives, states a-d per
// docs/build-pack/BUILD_SPEC.md §3.2). Token references match the
// pack's forest/lime light palette.
const styles = StyleSheet.create({
  container: { flex: 1, backgroundColor: colors.page },
  eyebrow: {
    color: colors.link,
    fontSize: 12,
    letterSpacing: 1.4,
    textTransform: 'uppercase',
    fontFamily: fonts.monoSemi,
  },
  h1: {
    color: colors.ink,
    fontSize: 28,
    fontWeight: '700',
    marginTop: 4,
    fontFamily: fonts.display,
    textTransform: 'uppercase',
  },
  muted: { color: colors.muted, fontSize: 14 },
  error: { color: colors.error, fontSize: 14 },
  card: {
    backgroundColor: colors.card,
    borderWidth: 1,
    borderColor: colors.rule,
    borderRadius: 14,
    padding: 16,
    gap: 6,
  },
  cardTitle: { color: colors.ink, fontSize: 16, fontWeight: '600' },
  cardMeta: { color: colors.muted, fontSize: 12 },
  cta: {
    borderWidth: 1,
    borderColor: colors.link,
    paddingVertical: 14,
    borderRadius: 10,
    alignItems: 'center',
  },
  ctaText: {
    color: colors.link,
    fontWeight: '700',
    textTransform: 'uppercase',
    letterSpacing: 1.2,
    fontFamily: fonts.display,
  },
});
