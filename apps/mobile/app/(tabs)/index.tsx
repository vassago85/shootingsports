import { useFocusEffect, useRouter } from 'expo-router';
import { useCallback, useMemo, useState } from 'react';
import { RefreshControl, ScrollView, StyleSheet, View } from 'react-native';

import { EventCard, listEvents } from '@/api/events';
import { colors } from '@/theme';
import { EmptyState, ErrorRow, GhostButton, HeroBand, MatchCard, SectionTitle, Spinner } from '@/ui';

/**
 * M2 Home — "the reason to open the app on a Friday".
 *
 * Phase 2 layout — three sections, in order of urgency:
 *
 *   1. "This weekend"  — horizontal carousel, Sat + Sun matches
 *   2. "Next 30 days"  — horizontal carousel, next 30 days (ex-weekend)
 *   3. "All upcoming"  — vertical MatchCards (first 6), plus the
 *                        "Browse the full calendar" ghost button
 *
 * Sections hide themselves when they'd be empty, so a Monday with no
 * weekend matches lands straight on "All upcoming" with no awkward
 * "This weekend (0)" header.
 *
 * One API call feeds all three sections — we fetch up to 60 upcoming
 * matches and bucket them client-side. Faster than three round-trips
 * and lets the user see counts without any UI shimmer.
 *
 * States per BUILD_SPEC §3.2:
 *   - M2a Loading:   HeroBand + centred Spinner
 *   - M2b Empty:     HeroBand + EmptyState (calendar-outline)
 *   - M2c Error:     HeroBand + ErrorRow + "Browse the full calendar"
 *   - M2d Populated: HeroBand + sections + ghost button
 */

/** Max horizontal cards per section — anything beyond needs the full
 *  Calendar screen with its filters. */
const CAROUSEL_LIMIT = 6;

/** Max all-upcoming cards shown on Home — keeps the vertical scroll
 *  from eating the whole screen. */
const ALL_UPCOMING_LIMIT = 6;

/** Width of each horizontally-scrolled MatchCard. Enough for a two-
 *  line title + meta without the row feeling cramped. */
const CARD_WIDTH = 280;

/**
 * Date helpers — kept inline so Home doesn't depend on Calendar's
 * `resolveDateRange`. The two live in the same mental model: start
 * of today, start of 30 days hence, local time.
 */
function startOfToday(): Date {
  const d = new Date();
  d.setHours(0, 0, 0, 0);
  return d;
}

function startOfTomorrow(): Date {
  const d = startOfToday();
  d.setDate(d.getDate() + 1);
  return d;
}

/** Saturday + Sunday of the current week (today if today is Sat/Sun). */
function thisWeekendRange(): { start: Date; end: Date } {
  const now = new Date();
  const dow = now.getDay(); // 0 = Sun, 6 = Sat
  const start = startOfToday();

  if (dow === 0) {
    // Sunday today — the whole weekend window is just today.
    const end = new Date(start);
    end.setHours(23, 59, 59, 999);
    return { start, end };
  }

  if (dow === 6) {
    // Saturday today — today + tomorrow.
    const end = new Date(start);
    end.setDate(start.getDate() + 1);
    end.setHours(23, 59, 59, 999);
    return { start, end };
  }

  // Weekday — jump to this coming Sat + Sun.
  const saturday = new Date(start);
  saturday.setDate(start.getDate() + (6 - dow));
  const sunday = new Date(saturday);
  sunday.setDate(saturday.getDate() + 1);
  sunday.setHours(23, 59, 59, 999);
  return { start: saturday, end: sunday };
}

/**
 * Split events into weekend / next-30-ex-weekend / all. Each slice
 * is capped so the carousels never balloon beyond something you'd
 * actually scroll through on a phone.
 */
function bucket(events: EventCard[]): {
  weekend: EventCard[];
  next30: EventCard[];
  all: EventCard[];
} {
  const { start: weStart, end: weEnd } = thisWeekendRange();
  const tomorrow = startOfTomorrow();
  const in30 = new Date(startOfToday());
  in30.setDate(in30.getDate() + 30);
  in30.setHours(23, 59, 59, 999);

  const weekend: EventCard[] = [];
  const next30: EventCard[] = [];

  for (const event of events) {
    if (event.starts_at === null) {
      continue;
    }
    const starts = new Date(event.starts_at);

    if (starts >= weStart && starts <= weEnd) {
      if (weekend.length < CAROUSEL_LIMIT) {
        weekend.push(event);
      }
      continue;
    }

    // "Next 30 days" excludes anything already in the weekend bucket.
    // We also skip events happening today when today itself isn't a
    // weekend day — the weekday carousel is forward-looking.
    if (starts >= tomorrow && starts <= in30) {
      if (next30.length < CAROUSEL_LIMIT) {
        next30.push(event);
      }
    }
  }

  return {
    weekend,
    next30,
    all: events.slice(0, ALL_UPCOMING_LIMIT),
  };
}

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

  const sections = useMemo(() => bucket(events), [events]);

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
