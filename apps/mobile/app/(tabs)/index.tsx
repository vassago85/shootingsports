import { Ionicons } from '@expo/vector-icons';
import { useFocusEffect, useRouter } from 'expo-router';
import { useCallback, useMemo, useState } from 'react';
import { Pressable, RefreshControl, ScrollView, StyleSheet, Text, View } from 'react-native';

import { bucketEvents } from '@/api/event-buckets';
import { EventCard, listEvents } from '@/api/events';
import { fetchMeFeed, FeedEntry, FeedReason, MeFeedResponse } from '@/api/me-feed';
import { getAuthToken } from '@/auth/store';
import { isHomePersonalisedEnabled } from '@/flags';
import { colors, fonts, radius } from '@/theme';
import {
  EmptyState,
  ErrorRow,
  GhostButton,
  HeroBand,
  MatchCard,
  SectionTitle,
  Spinner,
} from '@/ui';

/**
 * M2 Home.
 *
 * Two branches, chosen by whether a Sanctum token lives in SecureStore:
 *
 * 1. Signed OUT or signed in with zero follows
 *    → the classic generic layout (M2a–M2d from BUILD_SPEC §3.2):
 *      "National calendar / Upcoming matches" with two carousels
 *      pulled from /events?limit=60 and bucketed client-side.
 *
 * 2. Signed IN with at least one follow
 *    → the personalised layout (M2e):
 *      "For you / What's on" with three sections from /me/feed:
 *        · Saved (incl. a "Changed" badge when the server flagged
 *          the match as materially edited since the shooter saved it)
 *        · From clubs you follow (this_weekend + next_30_days)
 *      Each personalised card carries a reasons strip beneath it so
 *      the shooter can see why that match surfaced ("SAPRF · Gauteng").
 *
 * The "no follows yet" state keeps the generic layout but puts a
 * single nudge card on top of it so the user has a path to make the
 * feed useful. The "has follows but no province set" state keeps the
 * personalised layout and nudges toward settings.
 *
 * Signed-out behaviour deliberately matches the spec's rule: "Copy
 * stays generic and national in the MVP, so users don't infer
 * personalisation that isn't there."
 */

/** Width of each horizontally-scrolled MatchCard. Enough for a two-
 *  line title + meta without the row feeling cramped. */
const CARD_WIDTH = 280;

type ViewState =
  | { kind: 'loading' }
  | { kind: 'generic'; events: EventCard[]; error: string | null; signedIn: boolean }
  | {
      kind: 'personalised';
      feed: MeFeedResponse;
      error: string | null;
    };

export default function HomeScreen() {
  const router = useRouter();
  const [state, setState] = useState<ViewState>({ kind: 'loading' });
  const [refreshing, setRefreshing] = useState(false);

  const load = useCallback(async () => {
    // Fresh token check on every load — SecureStore reads are cheap
    // and the alternative (track signedIn in state) races with
    // useFocusEffect in the mounted-but-not-yet-settled window.
    const token = await getAuthToken();
    const signedIn = token !== null;
    // M2e lives behind a flag defaulting off (BUILD_SPEC §3.2 marks
    // it "Future"). When the flag is off the signed-in experience
    // falls back to the generic national calendar, same as a signed
    // out visitor — zero behavioural drift until we flip the flag.
    const usePersonalised = signedIn && isHomePersonalisedEnabled();

    try {
      if (usePersonalised) {
        const feed = await fetchMeFeed();
        setState({ kind: 'personalised', feed, error: null });
      } else {
        const { events } = await listEvents({ limit: 60 });
        setState({ kind: 'generic', events, error: null, signedIn });
      }
    } catch (err) {
      const message = err instanceof Error ? err.message : 'Could not load matches.';

      // On a signed-in failure fall through to a generic view with
      // the error shown — the user still gets *something* on Home
      // instead of a bare error screen.
      if (usePersonalised) {
        try {
          const { events } = await listEvents({ limit: 60 });
          setState({ kind: 'generic', events, error: message, signedIn: true });
        } catch {
          setState({ kind: 'generic', events: [], error: message, signedIn: true });
        }
      } else {
        setState({ kind: 'generic', events: [], error: message, signedIn });
      }
    } finally {
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
  const openFind = useCallback(() => router.push('/find'), [router]);
  const openMe = useCallback(() => router.push('/me'), [router]);

  const refresh = useCallback(() => {
    setRefreshing(true);
    void load();
  }, [load]);

  return (
    <ScrollView
      style={styles.container}
      refreshControl={
        <RefreshControl refreshing={refreshing} onRefresh={refresh} tintColor={colors.link} />
      }
    >
      {renderHero(state)}

      <View style={styles.body}>
        {state.kind === 'loading' ? (
          <Spinner label="Loading matches" />
        ) : state.kind === 'personalised' ? (
          <PersonalisedBody
            feed={state.feed}
            error={state.error}
            onMatchPress={openMatch}
            onOpenCalendar={openCalendar}
            onOpenMe={openMe}
            onOpenFind={openFind}
          />
        ) : (
          <GenericBody
            events={state.events}
            error={state.error}
            signedIn={state.signedIn}
            onMatchPress={openMatch}
            onOpenCalendar={openCalendar}
            onOpenFind={openFind}
          />
        )}
      </View>
    </ScrollView>
  );
}

function renderHero(state: ViewState) {
  if (state.kind === 'personalised') {
    return <HeroBand eyebrow="For you" title="What's on" />;
  }
  return <HeroBand eyebrow="National calendar" title="Upcoming matches" />;
}

/* ============================================================
 * Personalised branch — signed in with follows
 * ========================================================= */

function PersonalisedBody({
  feed,
  error,
  onMatchPress,
  onOpenCalendar,
  onOpenMe,
  onOpenFind,
}: {
  feed: MeFeedResponse;
  error: string | null;
  onMatchPress: (slug: string) => void;
  onOpenCalendar: () => void;
  onOpenMe: () => void;
  onOpenFind: () => void;
}) {
  const { saved, this_weekend: thisWeekend, next_30_days: next30 } = feed.data;
  const { has_follows: hasFollows, needs_province: needsProvince } = feed.meta;

  const totalFollowed = thisWeekend.length + next30.length;

  return (
    <>
      {error !== null && (
        <ErrorRow>Couldn't refresh everything. Showing what we had.</ErrorRow>
      )}

      {!hasFollows ? (
        // Shouldn't happen inside the personalised branch — the
        // controller sets has_follows:false, we end up in the
        // generic branch. Guard anyway so a schema drift doesn't
        // leave the user staring at a blank Home.
        <NudgeCard
          icon="heart-outline"
          title="Follow a club or discipline"
          body="Your personal home lights up once you follow something."
          actionLabel="Discover clubs"
          onPress={onOpenFind}
        />
      ) : null}

      {needsProvince && (
        <NudgeCard
          icon="location-outline"
          title="Pick your home province"
          body="So matches in the disciplines you follow can show up here."
          actionLabel="Open profile"
          onPress={onOpenMe}
        />
      )}

      {saved.length > 0 && (
        <PersonalisedSection
          title="Saved"
          events={saved.map((e) => ({ ...e, reasons: [] }))}
          onMatchPress={onMatchPress}
          showReasons={false}
          showChangedBadge
        />
      )}

      {thisWeekend.length > 0 && (
        <PersonalisedSection
          title="This weekend"
          events={thisWeekend}
          onMatchPress={onMatchPress}
          showReasons
        />
      )}

      {next30.length > 0 && (
        <PersonalisedSection
          title="Next 30 days"
          events={next30}
          onMatchPress={onMatchPress}
          showReasons
        />
      )}

      {hasFollows && totalFollowed === 0 && saved.length === 0 && (
        <EmptyState icon="calendar-outline">
          Nothing from your follows in the next 30 days. Browse the full calendar for other matches.
        </EmptyState>
      )}

      <View style={styles.ctaSection}>
        <GhostButton label="Browse the full calendar" onPress={onOpenCalendar} />
      </View>
    </>
  );
}

/* ============================================================
 * Generic branch — signed out, or signed in with no follows yet
 * ========================================================= */

function GenericBody({
  events,
  error,
  signedIn,
  onMatchPress,
  onOpenCalendar,
  onOpenFind,
}: {
  events: EventCard[];
  error: string | null;
  signedIn: boolean;
  onMatchPress: (slug: string) => void;
  onOpenCalendar: () => void;
  onOpenFind: () => void;
}) {
  const sections = useMemo(() => bucketEvents(events), [events]);

  // Full-screen error view only when the fallback has nothing to
  // show either. If the generic fetch has data, we show it with an
  // inline error row — losing the personalised feed shouldn't blank
  // the whole screen.
  if (error !== null && events.length === 0) {
    return (
      <>
        <ErrorRow>Couldn't load matches. Pull to refresh, or open the full calendar.</ErrorRow>
        <GhostButton label="Browse the full calendar" onPress={onOpenCalendar} />
      </>
    );
  }

  if (events.length === 0) {
    return (
      <EmptyState icon="calendar-outline">
        No upcoming matches yet. Pull to refresh.
      </EmptyState>
    );
  }

  return (
    <>
      {error !== null && (
        <ErrorRow>Couldn't refresh your personal feed. Showing the national calendar instead.</ErrorRow>
      )}

      {signedIn && (
        // Signed in but no follows yet — the server's /me/feed came
        // back has_follows:false and we're rendering generic. Nudge
        // the user toward Find so the next session can be personal.
        <NudgeCard
          icon="heart-outline"
          title="Follow a club or discipline"
          body="Your personal home lights up once you follow something."
          actionLabel="Discover clubs"
          onPress={onOpenFind}
        />
      )}

      {sections.weekend.length > 0 && (
        <CarouselSection
          title="This weekend"
          events={sections.weekend}
          onMatchPress={onMatchPress}
          onSeeAll={onOpenCalendar}
        />
      )}

      {sections.next30.length > 0 && (
        <CarouselSection
          title="Next 30 days"
          events={sections.next30}
          onMatchPress={onMatchPress}
          onSeeAll={onOpenCalendar}
        />
      )}

      <View style={styles.ctaSection}>
        <GhostButton label="Browse the full calendar" onPress={onOpenCalendar} />
      </View>
    </>
  );
}

/* ============================================================
 * Shared section renderers
 * ========================================================= */

/** The classic carousel section (title + "See all" + horizontal scroll). */
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

/**
 * Carousel section with per-card reasons strips and (optionally) a
 * "Changed" badge on saved matches. Used by the personalised feed.
 */
function PersonalisedSection({
  title,
  events,
  onMatchPress,
  showReasons,
  showChangedBadge = false,
}: {
  title: string;
  events: FeedEntry[];
  onMatchPress: (slug: string) => void;
  showReasons: boolean;
  showChangedBadge?: boolean;
}) {
  return (
    <View style={styles.section}>
      <View style={styles.sectionHead}>
        <SectionTitle>{title}</SectionTitle>
      </View>
      <ScrollView
        horizontal
        showsHorizontalScrollIndicator={false}
        contentContainerStyle={styles.carousel}
      >
        {events.map((event) => (
          <View key={event.id} style={styles.carouselCard}>
            {showChangedBadge && event.changed === true && (
              <View style={styles.changedBadge} accessibilityLabel="This match has changed since you saved it">
                <Text style={styles.changedBadgeText}>CHANGED</Text>
              </View>
            )}
            <MatchCard event={event} onPress={() => onMatchPress(event.slug)} />
            {showReasons && event.reasons.length > 0 && (
              <ReasonsStrip reasons={event.reasons} />
            )}
          </View>
        ))}
      </ScrollView>
    </View>
  );
}

/**
 * The per-match reasons strip. Mono tint pills with icon + name.
 * E.g. "♥ SAPRF · GP" or "⌾ Precision Rifle · GP".
 */
function ReasonsStrip({ reasons }: { reasons: FeedReason[] }) {
  return (
    <View style={styles.reasonsStrip}>
      {reasons.map((reason, i) => (
        <View key={`${reason.type}-${reason.name}-${i}`} style={styles.reasonPill}>
          <Ionicons
            name={reason.type === 'club' ? 'heart' : 'ellipse-outline'}
            size={12}
            color={colors.link}
            style={styles.reasonIcon}
          />
          <Text style={styles.reasonText} numberOfLines={1}>
            {reason.name}
            {reason.province !== null && reason.province !== '' ? ` · ${reason.province}` : ''}
          </Text>
        </View>
      ))}
    </View>
  );
}

/**
 * Thin inline card: icon + title + body + one action. Used for the
 * "follow something" and "pick a province" nudges above the feed.
 */
function NudgeCard({
  icon,
  title,
  body,
  actionLabel,
  onPress,
}: {
  icon: React.ComponentProps<typeof Ionicons>['name'];
  title: string;
  body: string;
  actionLabel: string;
  onPress: () => void;
}) {
  return (
    <Pressable
      onPress={onPress}
      accessibilityRole="button"
      accessibilityLabel={`${title}. ${body}. ${actionLabel}.`}
      style={({ pressed }) => [styles.nudge, pressed && styles.nudgePressed]}
    >
      <Ionicons name={icon} size={22} color={colors.link} style={styles.nudgeIcon} />
      <View style={styles.nudgeBody}>
        <Text style={styles.nudgeTitle}>{title}</Text>
        <Text style={styles.nudgeText}>{body}</Text>
        <Text style={styles.nudgeAction}>{actionLabel} →</Text>
      </View>
    </Pressable>
  );
}

const styles = StyleSheet.create({
  container: { flex: 1, backgroundColor: colors.page },

  body: { paddingVertical: 20, gap: 24 },

  section: { gap: 12 },
  sectionHead: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'flex-end',
    paddingHorizontal: 16,
  },
  carousel: { paddingHorizontal: 16, gap: 12 },
  carouselCard: { width: CARD_WIDTH, gap: 8 },

  ctaSection: { paddingHorizontal: 16, paddingTop: 4 },

  /* ---- Reasons strip under personalised cards ---- */
  reasonsStrip: {
    flexDirection: 'row',
    flexWrap: 'wrap',
    gap: 6,
  },
  reasonPill: {
    flexDirection: 'row',
    alignItems: 'center',
    backgroundColor: colors.tint,
    paddingHorizontal: 8,
    paddingVertical: 4,
    borderRadius: radius.chip,
    maxWidth: '100%',
  },
  reasonIcon: { marginRight: 4 },
  reasonText: {
    fontFamily: fonts.mono,
    fontSize: 11,
    color: colors.link,
    flexShrink: 1,
  },

  /* ---- "Changed" badge on saved cards ---- */
  changedBadge: {
    position: 'absolute',
    top: -6,
    right: -4,
    backgroundColor: colors.forest,
    paddingHorizontal: 8,
    paddingVertical: 3,
    borderRadius: radius.chip,
    zIndex: 2,
  },
  changedBadgeText: {
    fontFamily: fonts.monoSemi,
    fontSize: 10,
    color: colors.onForest,
    letterSpacing: 0.5,
  },

  /* ---- Nudge card ---- */
  nudge: {
    flexDirection: 'row',
    backgroundColor: colors.tint,
    borderRadius: radius.card,
    padding: 14,
    marginHorizontal: 16,
    gap: 12,
    alignItems: 'flex-start',
  },
  nudgePressed: { opacity: 0.7 },
  nudgeIcon: { marginTop: 2 },
  nudgeBody: { flex: 1, gap: 2 },
  nudgeTitle: {
    fontFamily: fonts.bodySemi,
    fontSize: 15,
    color: colors.ink,
  },
  nudgeText: {
    fontFamily: fonts.body,
    fontSize: 13,
    color: colors.muted,
  },
  nudgeAction: {
    fontFamily: fonts.monoSemi,
    fontSize: 12,
    color: colors.link,
    marginTop: 4,
  },
});
