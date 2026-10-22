import { Ionicons } from '@expo/vector-icons';
import { Stack, useLocalSearchParams, useRouter } from 'expo-router';
import * as WebBrowser from 'expo-web-browser';
import { useCallback, useEffect, useState } from 'react';
import {
  Alert,
  Pressable,
  ScrollView,
  Share,
  StyleSheet,
  Text,
  View,
} from 'react-native';

import { EventDetail, fetchEvent } from '@/api/events';
import { listSavedEvents, saveEvent, unsaveEvent } from '@/api/saved-events';
import { getAuthToken } from '@/auth/store';
import { colors, fonts } from '@/theme';
import { ErrorRow, GhostButton, HeroBand, PrimaryButton, Spinner } from '@/ui';

/**
 * R1 / R2 Match detail — the shared deep-dive screen pushed from Home,
 * Calendar, and Me's saved list.
 *
 * Per BUILD_SPEC §3.1 the final nav shape is a single `MatchDetail`
 * component mounted inside both the Home stack and the Calendar stack
 * so Back lands on the correct parent. Phase 1 is still pre-nav-
 * restructure, so this file lives at the stack root and uses a `from`
 * param to label the back button ("‹ Home" / "‹ Calendar" / "‹ Me").
 *
 * Spec R1/R2:
 *   - HeroBand: discipline eyebrow, match title, `date · range, province`
 *   - Details list: Date, Range, Province, Host, Discipline
 *   - Share button (native sheet) in the header right
 *   - PrimaryButton "Save match" / "Saved" (bookmark icon when saved)
 *   - GhostButton "Open match page" → website in in-app browser
 *   - Save-while-signed-out opens M1 Sign in. (Continuation of the save
 *     after sign-in is a backlog item — the user re-taps Save after
 *     signing in for now.)
 */

type ParentTab = 'home' | 'calendar' | 'me';

type Params = {
  slug: string;
  /** Set by the caller so Back reads "‹ Home" etc. Falls back to "Back". */
  from?: ParentTab;
};

const BACK_TITLES: Record<ParentTab, string> = {
  home: 'Home',
  calendar: 'Calendar',
  me: 'Me',
};

function formatHeroDate(iso: string | null): string {
  if (iso === null) {
    return 'Date TBC';
  }

  const date = new Date(iso);
  const weekday = date.toLocaleDateString('en-ZA', { weekday: 'short' }).toUpperCase();
  const day = date.getDate();
  const month = date.toLocaleDateString('en-ZA', { month: 'short' }).toUpperCase();
  return `${weekday} ${day} ${month}`;
}

function formatFullDate(iso: string | null): string {
  if (iso === null) {
    return 'TBC';
  }

  return new Date(iso).toLocaleDateString('en-ZA', {
    weekday: 'long',
    day: 'numeric',
    month: 'long',
    year: 'numeric',
  });
}

export default function MatchDetailScreen() {
  const router = useRouter();
  const { slug, from } = useLocalSearchParams<Params>();
  const [event, setEvent] = useState<EventDetail | null>(null);
  const [saved, setSaved] = useState<boolean | null>(null);
  const [savingToggle, setSavingToggle] = useState(false);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

  const load = useCallback(async () => {
    if (typeof slug !== 'string') {
      setError('Missing match slug.');
      setLoading(false);
      return;
    }

    setError(null);

    try {
      const detail = await fetchEvent(slug);
      setEvent(detail);

      const token = await getAuthToken();
      if (token !== null) {
        const savedEvents = await listSavedEvents();
        setSaved(savedEvents.some((row) => row.id === detail.id));
      } else {
        setSaved(false);
      }
    } catch (err) {
      setError(err instanceof Error ? err.message : 'Could not load this match.');
    } finally {
      setLoading(false);
    }
  }, [slug]);

  useEffect(() => {
    void load();
  }, [load]);

  const toggleSaved = useCallback(async () => {
    if (event === null || savingToggle) {
      return;
    }

    const token = await getAuthToken();
    if (token === null) {
      // Signed-out path per spec §3.2 R-notes: "Saving while signed out
      // opens M1". The full continuation (auto-save after sign-in) is a
      // backlog item.
      router.push('/login');
      return;
    }

    setSavingToggle(true);
    try {
      if (saved === true) {
        await unsaveEvent(event.id);
        setSaved(false);
      } else {
        await saveEvent(event.id);
        setSaved(true);
      }
    } catch (err) {
      Alert.alert(
        'Could not update',
        err instanceof Error ? err.message : "Please try again.",
      );
    } finally {
      setSavingToggle(false);
    }
  }, [event, saved, savingToggle, router]);

  const openMatchPage = useCallback(() => {
    if (event === null) {
      return;
    }
    void WebBrowser.openBrowserAsync(event.url).catch(() => {
      Alert.alert('Could not open', 'Try opening shootingsports.co.za in your browser.');
    });
  }, [event]);

  const share = useCallback(async () => {
    if (event === null) {
      return;
    }
    try {
      await Share.share({
        title: event.title,
        message: `${event.title}\n${event.url}`,
        url: event.url,
      });
    } catch {
      // Share sheet dismissed or unavailable — no user-facing error.
    }
  }, [event]);

  const backTitle = from !== undefined ? BACK_TITLES[from] : 'Back';

  const headerOptions = {
    title: 'Match',
    headerBackTitle: backTitle,
    headerRight: () =>
      event !== null ? (
        <Pressable
          onPress={() => void share()}
          hitSlop={10}
          accessibilityRole="button"
          accessibilityLabel="Share match"
          style={({ pressed }) => [styles.headerBtn, pressed && { opacity: 0.6 }]}
        >
          <Ionicons name="share-outline" size={22} color={colors.onForest} />
        </Pressable>
      ) : null,
  };

  if (loading) {
    return (
      <>
        <Stack.Screen options={headerOptions} />
        <View style={styles.center}>
          <Spinner label="Loading match" />
        </View>
      </>
    );
  }

  if (error !== null || event === null) {
    return (
      <>
        <Stack.Screen options={headerOptions} />
        <View style={styles.centerPad}>
          <ErrorRow>{error ?? "That match couldn't be found."}</ErrorRow>
          <GhostButton
            label="Try again"
            onPress={() => {
              setLoading(true);
              void load();
            }}
          />
        </View>
      </>
    );
  }

  // All relation accesses are optional-chained — the API resource
  // uses `whenLoaded` for several of these keys, so a controller
  // path that forgets to eager-load would drop them silently and
  // crash the detail screen on render.
  const discipline = event.disciplines?.[0];
  const venue = event.venue ?? null;
  const rangeName = venue?.name ?? event.venues?.[0]?.name ?? 'TBC';
  const province = venue?.province ?? event.venues?.[0]?.province ?? 'TBC';
  const hostName = event.host?.name ?? '';
  const metaLine = `${formatHeroDate(event.starts_at)} · ${rangeName}, ${province}`;

  return (
    <>
      <Stack.Screen options={headerOptions} />
      <ScrollView style={styles.container}>
        <HeroBand eyebrow={discipline?.name ?? 'Match'} title={event.title}>
          <Text style={styles.heroMeta}>{metaLine}</Text>
        </HeroBand>

        <View style={styles.body}>
          <View style={styles.details}>
            <DetailRow label="Date" value={formatFullDate(event.starts_at)} />
            <DetailRow label="Range" value={rangeName} />
            <DetailRow label="Province" value={province} />
            <DetailRow label="Host" value={hostName} />
            <DetailRow label="Discipline" value={discipline?.name ?? 'TBC'} />
          </View>

          {event.description !== null && event.description.length > 0 && (
            <View style={styles.descBlock}>
              <Text style={styles.descText}>{event.description}</Text>
            </View>
          )}

          <View style={styles.actions}>
            {saved === true ? (
              <GhostButton
                label="Saved"
                onPress={() => void toggleSaved()}
                accessibilityLabel="Saved. Tap to remove from saved."
              />
            ) : (
              <PrimaryButton
                label="Save match"
                onPress={() => void toggleSaved()}
                loading={savingToggle}
              />
            )}
            <GhostButton label="Open match page" onPress={openMatchPage} />
          </View>
        </View>
      </ScrollView>
    </>
  );
}

type DetailRowProps = { label: string; value: string };

function DetailRow({ label, value }: DetailRowProps) {
  return (
    <View style={styles.detailRow}>
      <Text style={styles.detailLabel}>{label}</Text>
      <Text style={styles.detailValue}>{value}</Text>
    </View>
  );
}

const styles = StyleSheet.create({
  container: { flex: 1, backgroundColor: colors.page },
  center: {
    flex: 1,
    alignItems: 'center',
    justifyContent: 'center',
    backgroundColor: colors.page,
  },
  centerPad: {
    flex: 1,
    alignItems: 'stretch',
    justifyContent: 'center',
    backgroundColor: colors.page,
    padding: 24,
    gap: 16,
  },

  heroMeta: {
    fontFamily: fonts.mono,
    fontSize: 13,
    color: colors.onForestMuted,
    marginTop: 4,
  },

  body: { padding: 16, gap: 20 },

  /* Details list — label / value rows with hair-rule between. The
   * last row's own bottom border is clipped by `overflow: hidden` so
   * the card's rounded corners stay clean. */
  details: {
    backgroundColor: colors.card,
    borderRadius: 14,
    borderWidth: 1,
    borderColor: colors.rule,
    paddingHorizontal: 16,
    overflow: 'hidden',
  },
  detailRow: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'flex-start',
    paddingVertical: 12,
    borderBottomWidth: 1,
    borderBottomColor: colors.rule,
    gap: 16,
  },
  detailLabel: {
    fontFamily: fonts.monoSemi,
    fontSize: 11,
    color: colors.muted,
    letterSpacing: 1.4,
    textTransform: 'uppercase',
    paddingTop: 2,
  },
  detailValue: {
    fontFamily: fonts.bodyMedium,
    fontSize: 15,
    color: colors.ink,
    flex: 1,
    textAlign: 'right',
    lineHeight: 20,
  },

  /* Match description — plain prose below the details list. */
  descBlock: {
    paddingHorizontal: 4,
  },
  descText: {
    fontFamily: fonts.body,
    fontSize: 15,
    lineHeight: 22,
    color: colors.ink,
  },

  actions: { gap: 12 },

  headerBtn: {
    paddingHorizontal: 10,
    paddingVertical: 6,
  },
});
