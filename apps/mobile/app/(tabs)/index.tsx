import { useFocusEffect, useRouter } from 'expo-router';
import { useCallback, useState } from 'react';
import { RefreshControl, ScrollView, StyleSheet, View } from 'react-native';

import { EventCard, listEvents } from '@/api/events';
import { colors } from '@/theme';
import { EmptyState, ErrorRow, GhostButton, HeroBand, MatchCard, Spinner } from '@/ui';

/**
 * M2 Home — "the reason to open the app on a Friday".
 *
 * States per BUILD_SPEC §3.2:
 *   - M2a Loading:   HeroBand + centred Spinner
 *   - M2b Empty:     HeroBand + EmptyState (calendar-outline)
 *   - M2c Error:     HeroBand + ErrorRow; "Browse the full calendar"
 *                    button stays visible per spec
 *   - M2d Populated: HeroBand + 6 MatchCards + ghost
 *                    "Browse the full calendar"
 *
 * `GET /api/v1/events` with default filters and a limit of 6. Pull to
 * refresh. `useFocusEffect` re-loads on tab focus so returning from a
 * match detail or another tab shows fresh content. Tapping a card
 * pushes R1 (match detail).
 *
 * Future M2e personalised (eyebrow "For you", followed-sport chips,
 * "From what you follow" + "Also nationally" sections) is behind a
 * flag; needs follows + saved-search endpoints that don't exist yet.
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

  useFocusEffect(
    useCallback(() => {
      void load();
    }, [load]),
  );

  const openCalendar = useCallback(() => router.push('/calendar'), [router]);
  const openMatch = useCallback(
    (slug: string) =>
      router.push({ pathname: '/matches/[slug]', params: { slug, from: 'home' } }),
    [router],
  );

  return (
    <ScrollView
      style={styles.container}
      refreshControl={
        <RefreshControl
          refreshing={refreshing}
          onRefresh={() => {
            setRefreshing(true);
            void load();
          }}
          tintColor={colors.link}
        />
      }
    >
      <HeroBand eyebrow="National calendar" title="Upcoming matches" />

      <View style={styles.body}>
        {loading ? (
          <Spinner label="Loading matches" />
        ) : error !== null ? (
          <>
            <ErrorRow>
              Couldn't load matches. Pull to refresh, or open the full calendar.
            </ErrorRow>
            <GhostButton label="Browse the full calendar" onPress={openCalendar} />
          </>
        ) : events.length === 0 ? (
          <EmptyState icon="calendar-outline">
            No upcoming matches yet. Pull to refresh.
          </EmptyState>
        ) : (
          <>
            <View style={styles.cards}>
              {events.map((event) => (
                <MatchCard key={event.id} event={event} onPress={() => openMatch(event.slug)} />
              ))}
            </View>
            <GhostButton label="Browse the full calendar" onPress={openCalendar} />
          </>
        )}
      </View>
    </ScrollView>
  );
}

const styles = StyleSheet.create({
  container: { flex: 1, backgroundColor: colors.page },
  body: { padding: 16, gap: 16 },
  cards: { gap: 12 },
});
