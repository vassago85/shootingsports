import { Ionicons } from '@expo/vector-icons';
import { Stack, useLocalSearchParams, useRouter } from 'expo-router';
import * as WebBrowser from 'expo-web-browser';
import { useCallback, useEffect, useState } from 'react';
import {
  Alert,
  Image,
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
 * Phase 2 layout:
 *
 *   [banner image, 180pt]            ← only if event.banner_url present
 *   [HeroBand: discipline / title / date · range, province]
 *   [host card: 48pt logo | host name / HOST eyebrow]
 *   [details list: Date / Range / Province / Discipline]
 *   [description prose, when present]
 *   [primary action: Save match / Saved]
 *   [secondary row: Open match page | Share]
 *
 * `from` param labels the back button ("‹ Home" / "‹ Calendar" / "‹ Me").
 * Signed-out save routes to login — the continuation-after-signin flow
 * is a backlog item.
 */

type ParentTab = 'home' | 'calendar' | 'me';

type Params = {
  slug: string;
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
  const [bannerFailed, setBannerFailed] = useState(false);

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
      setBannerFailed(false);

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
        err instanceof Error ? err.message : 'Please try again.',
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
  // path that forgets to eager-load would drop them silently.
  const discipline = event.disciplines?.[0];
  const venue = event.venue ?? null;
  const rangeName = venue?.name ?? event.venues?.[0]?.name ?? 'TBC';
  const province = venue?.province ?? event.venues?.[0]?.province ?? 'TBC';
  const hostName = event.host?.name ?? '';
  const hostLogo = event.host?.organisation?.logo_url ?? null;
  const metaLine = `${formatHeroDate(event.starts_at)} · ${rangeName}, ${province}`;

  const showBanner =
    typeof event.banner_url === 'string' &&
    event.banner_url.length > 0 &&
    !bannerFailed;

  return (
    <>
      <Stack.Screen options={headerOptions} />
      <ScrollView style={styles.container}>
        {showBanner && (
          <Image
            source={{ uri: event.banner_url as string }}
            style={styles.banner}
            resizeMode="cover"
            onError={() => setBannerFailed(true)}
            accessibilityIgnoresInvertColors
          />
        )}

        <HeroBand eyebrow={discipline?.name ?? 'Match'} title={event.title}>
          <Text style={styles.heroMeta}>{metaLine}</Text>
        </HeroBand>

        <View style={styles.body}>
          {/* Host card — organisation logo + name + "HOST" eyebrow.
              Falls back gracefully if the host has no logo yet. */}
          <View style={styles.hostCard}>
            {hostLogo !== null ? (
              <Image
                source={{ uri: hostLogo }}
                style={styles.hostLogo}
                resizeMode="contain"
                accessibilityIgnoresInvertColors
              />
            ) : (
              <View style={[styles.hostLogo, styles.hostLogoFallback]}>
                <Ionicons name="shield-outline" size={22} color={colors.link} />
              </View>
            )}
            <View style={styles.hostText}>
              <Text style={styles.hostEyebrow}>HOST</Text>
              <Text style={styles.hostName} numberOfLines={2}>
                {hostName}
              </Text>
            </View>
          </View>

          <View style={styles.details}>
            <DetailRow label="Date" value={formatFullDate(event.starts_at)} />
            <DetailRow label="Range" value={rangeName} />
            <DetailRow label="Province" value={province} />
            <DetailRow label="Discipline" value={discipline?.name ?? 'TBC'} />
          </View>

          {event.description !== null && event.description.length > 0 && (
            <View style={styles.descBlock}>
              <Text style={styles.descText}>{event.description}</Text>
            </View>
          )}

          {/* Primary save action — full-width. */}
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

          {/* Secondary actions — side by side, both ghost. Share is
              visible here instead of hidden in a header icon so first-
              time users notice it. */}
          <View style={styles.secondaryActions}>
            <View style={styles.secondaryAction}>
              <GhostButton label="Open match page" onPress={openMatchPage} />
            </View>
            <View style={styles.secondaryAction}>
              <GhostButton
                label="Share"
                onPress={() => void share()}
                accessibilityLabel="Share this match"
              />
            </View>
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

  /* Banner — full-bleed poster shot above the HeroBand. Hidden when
   * the API returns no banner_url, or when the image fails to load. */
  banner: {
    width: '100%',
    height: 180,
    backgroundColor: colors.forest,
  },

  heroMeta: {
    fontFamily: fonts.mono,
    fontSize: 13,
    color: colors.onForestMuted,
    marginTop: 4,
  },

  body: { padding: 16, gap: 20 },

  /* Host card — logo + name. Treated as its own band to give the
   * event its clear "who's running this" signal before the details. */
  hostCard: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 14,
    padding: 14,
    backgroundColor: colors.card,
    borderRadius: 14,
    borderWidth: 1,
    borderColor: colors.rule,
  },
  hostLogo: {
    width: 48,
    height: 48,
    borderRadius: 8,
    backgroundColor: colors.tint,
  },
  hostLogoFallback: {
    alignItems: 'center',
    justifyContent: 'center',
  },
  hostText: {
    flex: 1,
    gap: 2,
  },
  hostEyebrow: {
    fontFamily: fonts.monoSemi,
    fontSize: 10,
    color: colors.muted,
    letterSpacing: 1.4,
    textTransform: 'uppercase',
  },
  hostName: {
    fontFamily: fonts.bodySemi,
    fontSize: 15,
    color: colors.ink,
    lineHeight: 20,
  },

  /* Details list — label / value rows with hair-rule between. The
   * last row's own bottom border is clipped by `overflow: hidden`
   * so the card's rounded corners stay clean. */
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

  /* Secondary actions — Open + Share side by side, both ghost. */
  secondaryActions: {
    flexDirection: 'row',
    gap: 10,
  },
  secondaryAction: { flex: 1 },
});
