import { useFocusEffect, useRouter } from 'expo-router';
import { useCallback, useMemo, useState } from 'react';
import { RefreshControl, ScrollView, StyleSheet, View } from 'react-native';

import { bucketEvents } from '@/api/event-buckets';
import { EventCard, listEvents } from '@/api/events';
import { colors } from '@/theme';
import { EmptyState, ErrorRow, GhostButton, HeroBand, MatchCard, SectionTitle, Spinner } from '@/ui';

/**
 * M2 Home — "the reason to open the app on a Friday".
 *
 * Three sections, in order of urgency:
 *
 *   1. "This weekend"  — Fri–Sun matches (ThisWeekend window)
 *   2. "Next 30 days"  — remaining upcoming within 30 days
 *   3. "All upcoming"  — vertical MatchCards (first 6), plus the
 *                        "Browse the full calendar" ghost button
 *
 * Sections hide themselves when they'd be empty, so a Monday with no
 * weekend matches lands straight on "All upcoming".
 *
 * One API call feeds all three sections — see `bucketEvents` for the
 * weekend-overlap and 30-day rules.
 *
 * States per BUILD_SPEC §3.2:
 *   - M2a Loading:   HeroBand + centred Spinner
 *   - M2b Empty:     HeroBand + EmptyState (calendar-outline)
 *   - M2c Error:     HeroBand + ErrorRow + "Browse the full calendar"
 *   - M2d Populated: HeroBand + sections + ghost button
 */

/** Width of each horizontally-scrolled MatchCard. Enough for a two-
 *  line title + meta without the row feeling cramped. */
const CARD_WIDTH = 280;

export default function HomeScreen() {
  const router = useRouter();
  const [events, setEvents] = useState<EventCard[]>([]);
  const [loading, setLoading] = useState(true);
  const [refreshing, setRefreshing] = useState(false);
  const [error, setError] = useState<string | null>(null);

  const load = useCallback(async () => {
    setError(null);
    try {
      // One wide fetch (60 rows); client-side bucket into the three
      // sections. Faster than three parallel requests and lets the
      // "All upcoming" slice stay in sync with Calendar's defaults.
      const { events: rows } = await listEvents({ limit: 60 });
      setEvents(rows);
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

  const sections = useMemo(() => bucketEvents(events), [events]);

  const openCalendar = useCallback(() => router.push('/calendar'), [router]);
  const openCalendarWithDate = useCallback(
    (_label: 'this-weekend' | 'next-30') => {
      // "See all" from a section jumps to Calendar — Phase 3 will
      // pre-apply the matching date filter via a route param, but
      // for now the plain Calendar is enough.
      router.push('/calendar');
    },
    [router],
  );
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
            {sections.weekend.length > 0 && (
              <CarouselSection
                title="This weekend"
                events={sections.weekend}
                onMatchPress={openMatch}
                onSeeAll={() => openCalendarWithDate('this-weekend')}
              />
            )}

            {sections.next30.length > 0 && (
              <CarouselSection
                title="Next 30 days"
                events={sections.next30}
                onMatchPress={openMatch}
                onSeeAll={() => openCalendarWithDate('next-30')}
              />
            )}

            <View style={styles.allSection}>
              <SectionTitle>All upcoming</SectionTitle>
              <View style={styles.cards}>
                {sections.all.map((event) => (
                  <MatchCard
                    key={event.id}
                    event={event}
                    onPress={() => openMatch(event.slug)}
                  />
                ))}
              </View>
              <GhostButton label="Browse the full calendar" onPress={openCalendar} />
            </View>
          </>
        )}
      </View>
    </ScrollView>
  );
}

/**
 * One horizontal section — title + "See all →" link + a sideways-
 * scrolling row of fixed-width MatchCards. Used by Home for the
 * "This weekend" and "Next 30 days" bands.
 */
function CarouselSection({
  title,
  events,
  onMatchPress,
  onSeeAll,
}: {
  title: string;
  events: EventCard[];
  onMatchPress: (slug: string) => void;
  onSeeAll: () => void;
}) {
  return (
    <View style={styles.section}>
      <View style={styles.sectionHead}>
        <SectionTitle>{title}</SectionTitle>
        <GhostButton label="See all →" onPress={onSeeAll} compact />
      </View>
      <ScrollView
        horizontal
        showsHorizontalScrollIndicator={false}
        contentContainerStyle={styles.carousel}
      >
        {events.map((event) => (
          <View key={event.id} style={styles.carouselCard}>
            <MatchCard event={event} onPress={() => onMatchPress(event.slug)} />
          </View>
        ))}
      </ScrollView>
    </View>
  );
}

const styles = StyleSheet.create({
  container: { flex: 1, backgroundColor: colors.page },

  /* Overall page padding — kept generous so sections feel distinct. */
  body: { paddingVertical: 20, gap: 24 },

  /* Horizontal carousel sections. The title row sits in page padding
   * but the ScrollView is edge-to-edge so cards can bleed off the
   * right side (nice "there's more" affordance). */
  section: { gap: 12 },
  sectionHead: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'flex-end',
    paddingHorizontal: 16,
  },
  carousel: {
    paddingHorizontal: 16,
    gap: 12,
  },
  carouselCard: {
    width: CARD_WIDTH,
  },

  /* All-upcoming vertical section. */
  allSection: {
    paddingHorizontal: 16,
    gap: 16,
  },
  cards: { gap: 12 },
});
