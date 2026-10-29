import { Ionicons } from '@expo/vector-icons';
import * as Linking from 'expo-linking';
import { Stack, useLocalSearchParams, useRouter } from 'expo-router';
import * as WebBrowser from 'expo-web-browser';
import { useCallback, useEffect, useState } from 'react';
import {
  Alert,
  Image,
  Platform,
  Pressable,
  ScrollView,
  Share,
  StyleSheet,
  Text,
  View,
} from 'react-native';

import { formatMatchDateFull } from '@/api/dates';
import { EventDetail, fetchEvent } from '@/api/events';
import { listSavedEvents, saveEvent, unsaveEvent } from '@/api/saved-events';
import { getAuthToken } from '@/auth/store';
import { colors, fonts } from '@/theme';
import { ErrorRow, GhostButton, HeroBand, LinkableText, PrimaryButton, Spinner } from '@/ui';

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

/* Date rendering is centralised in `@/api/dates` so a two-day match
 * reads the same way on Home, Calendar, and the detail hero. */

export default function MatchDetailScreen() {
  const router = useRouter();
  const { slug, from } = useLocalSearchParams<Params>();
  const [event, setEvent] = useState<EventDetail | null>(null);
  const [saved, setSaved] = useState<boolean | null>(null);
  const [savingToggle, setSavingToggle] = useState(false);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [bannerFailed, setBannerFailed] = useState(false);
  const [bannerOpen, setBannerOpen] = useState(false);

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

  const openDirections = useCallback(() => {
    if (event === null) {
      return;
    }
    const url = buildDirectionsUrl(event);
    if (url === null) {
      return;
    }
    void Linking.openURL(url).catch(() => {
      Alert.alert(
        'Could not open directions',
        'Please check that a maps app is installed.',
      );
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

  /**
   * Always-visible back control. When the stack has history we pop,
   * otherwise (deep link, shared URL opened cold) we replace with Home
   * so a shooter is never dumped onto a dead-end screen with nowhere
   * to go. `replace` instead of `push` avoids leaving the match
   * underneath Home in the stack, which would show a stale back arrow
   * on Home itself.
   */
  const goBack = useCallback(() => {
    if (router.canGoBack()) {
      router.back();
      return;
    }
    router.replace('/');
  }, [router]);

  const headerOptions = {
    title: 'Match',
    headerBackTitle: backTitle,
    headerBackVisible: false,
    headerLeft: () => (
      <Pressable
        onPress={goBack}
        accessibilityRole="button"
        accessibilityLabel={`Back to ${backTitle}`}
        hitSlop={10}
        style={styles.headerBack}
      >
        {/* White on the forest-green header — the previous `colors.ink`
         *  (near-black) rendered the chevron and label effectively
         *  invisible against the dark background. */}
        <Ionicons name="chevron-back" size={26} color={colors.onForest} />
        <Text style={styles.headerBackLabel}>{backTitle}</Text>
      </Pressable>
    ),
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
  // Hero subtitle: the long verbose date so the detail header reads
  // as a complete sentence ("Saturday 31 October 2026 · Darling · WC").
  // The same formatter runs in the facts "Date" row below so a shooter
  // never sees two different renderings of the match date on one page.
  const dateLabel = formatMatchDateFull(event.starts_at, event.ends_at);
  const metaLine = `${dateLabel} · ${event.location_label}`;

  const showBanner =
    typeof event.banner_url === 'string' &&
    event.banner_url.length > 0 &&
    !bannerFailed;

  // Hide the Directions button entirely when the venue has no
  // coordinates and no address — any URL we built would open an
  // empty map search and look broken.
  const canShowDirections = buildDirectionsUrl(event) !== null;

  return (
    <View style={styles.pageRoot}>
      <Stack.Screen options={headerOptions} />
      <ScrollView style={styles.container}>
        {showBanner && (
          // `contain` so the match poster is never cropped — the
          // whole image sits inside a dark forest band at the top.
          // Tapping it opens a full-screen modal viewer; a second tap
          // (or system back) dismisses. We stop short of pinch-zoom
          // for Expo Go compatibility — any pinch library would pull
          // in a native module that Go can't resolve.
          <Pressable
            onPress={() => setBannerOpen(true)}
            accessibilityRole="imagebutton"
            accessibilityLabel={`${event.title} poster. Open full screen.`}
          >
            <View style={styles.bannerFrame}>
              <Image
                source={{ uri: event.banner_url as string }}
                style={styles.banner}
                resizeMode="contain"
                onError={() => setBannerFailed(true)}
                accessibilityIgnoresInvertColors
              />
            </View>
          </Pressable>
        )}

        <HeroBand eyebrow={discipline?.name ?? 'Match'} title={event.title}>
          {/* numberOfLines=2 so a date + long multi-token location
              can wrap rather than lose its province. */}
          <Text style={styles.heroMeta} numberOfLines={2}>
            {metaLine}
          </Text>
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
            <DetailRow
              label="Date"
              value={formatMatchDateFull(event.starts_at, event.ends_at)}
            />
            <DetailRow label="Range" value={rangeName} />
            <DetailRow label="Province" value={province} />
            <DetailRow
              label="Discipline"
              value={discipline?.name ?? 'TBC'}
              isLast
            />
          </View>

          {event.description !== null && event.description.length > 0 && (
            <View style={styles.descBlock}>
              {/* LinkableText turns SA phone numbers and emails in
                  the prose into tap targets so a shooter can call
                  the MD or email the host without copy-pasting out
                  of the paragraph. */}
              <LinkableText style={styles.descText}>
                {event.description}
              </LinkableText>
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

          {/* Directions — hidden when the venue has no coordinates
              AND no address. Prefers lat/lng for a precise pin;
              falls back to a text search of the address. iOS opens
              Apple Maps; Android opens the user's default geo:
              handler (Google Maps, Waze, etc.). */}
          {canShowDirections && (
            <GhostButton
              label="Directions"
              onPress={openDirections}
              iconRight="navigate-outline"
              accessibilityLabel={`Open directions to ${rangeName}`}
            />
          )}

          {/* Secondary actions — View on website + Share side by side,
              both ghost. "View on website" uses expo-web-browser
              (in-app browser chrome) and shows an external-link glyph
              so first-time users know it leaves the app. Share is
              visible here instead of hidden in a header icon so it's
              discoverable. */}
          <View style={styles.secondaryActions}>
            <View style={styles.secondaryAction}>
              <GhostButton
                // Shortened from "View on website" so the label fits
                // on one line in the half-width button, side by side
                // with Share.
                label="Website"
                onPress={openMatchPage}
                iconRight="open-outline"
                accessibilityLabel="View this match on shootingsports.co.za"
              />
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

      {/* Full-screen poster viewer. Taps anywhere dismiss. No pinch
          zoom here (that would need a native-only gesture library
          that breaks Expo Go) — contain-fit on a 90% viewport is
          enough to read every detail on a match poster.
          Implemented as an absolute-fill overlay instead of RN Modal
          so react-native-web's Modal-portal stacking quirks (which
          leave the backdrop sitting behind the ScrollView) can't
          interfere; native sees the same tree and renders the same
          black-over-everything overlay. */}
      {showBanner && bannerOpen && (
        <PosterViewer
          uri={event.banner_url as string}
          onClose={() => setBannerOpen(false)}
        />
      )}
    </View>
  );
}

/**
 * Full-screen poster viewer.
 *
 * On web, mounts into document.body via React DOM's createPortal so
 * the backdrop escapes Expo Router's Screen stacking context — which
 * sits inside a positioned ancestor and gives sibling z-index wars
 * on react-native-web. On native (iOS/Android), there's no portal
 * needed: the overlay renders inline above its parent ScrollView via
 * `position: absolute` + `elevation`, which both platforms honour.
 *
 * Keeps the overlay a React tree sibling of the detail page, so
 * state (bannerOpen) and event hooks (`onClose`) stay colocated.
 */
type PosterViewerProps = { uri: string; onClose: () => void };

function PosterViewer({ uri, onClose }: PosterViewerProps) {
  const overlay = (
    <View style={styles.viewerBackdrop}>
      <Pressable
        style={StyleSheet.absoluteFill}
        onPress={onClose}
        accessibilityRole="button"
        accessibilityLabel="Close poster viewer"
      />
      <Image
        source={{ uri }}
        style={styles.viewerImage}
        resizeMode="contain"
        accessibilityIgnoresInvertColors
        pointerEvents="none"
      />
    </View>
  );

  if (Platform.OS === 'web' && typeof document !== 'undefined') {
    // Lazy require react-dom only on web; native builds don't ship it.
    const { createPortal } = require('react-dom');
    return createPortal(overlay, document.body);
  }

  return overlay;
}

/**
 * Build a platform-appropriate maps URL for the Directions button.
 *
 * Preference order:
 *
 *   1. Venue coordinates (lat, lng)      — precise pin
 *   2. Venue address (text search)       — approximate, user picks
 *   3. null                              — hide the button
 *
 * On iOS we use the Apple Maps URL scheme (`maps://`) which the
 * system intercepts and routes to Maps directly. On Android we use
 * the Google-blessed `geo:` scheme which lets the user's default
 * handler (Google Maps / Waze / HERE) resolve the pin. On web (dev
 * mode) we fall back to the universal Google Maps HTTPS URL so the
 * link still works outside of a mobile OS.
 */
function buildDirectionsUrl(event: EventDetail): string | null {
  const venue = event.venue ?? event.venues?.[0] ?? null;
  if (venue === null) {
    return null;
  }

  const hasCoords =
    typeof venue.lat === 'number' &&
    typeof venue.lng === 'number' &&
    Number.isFinite(venue.lat) &&
    Number.isFinite(venue.lng);
  const hasAddress =
    typeof venue.address === 'string' && venue.address.trim().length > 0;

  if (!hasCoords && !hasAddress) {
    return null;
  }

  const label = encodeURIComponent(venue.name);

  if (hasCoords) {
    const lat = venue.lat as number;
    const lng = venue.lng as number;

    if (Platform.OS === 'ios') {
      return `maps://?daddr=${lat},${lng}&q=${label}`;
    }
    if (Platform.OS === 'android') {
      return `geo:${lat},${lng}?q=${lat},${lng}(${label})`;
    }
    return `https://www.google.com/maps/dir/?api=1&destination=${lat},${lng}`;
  }

  // Address-only fallback — let the maps app search for the string.
  const address = (venue.address as string).trim();
  const query = encodeURIComponent(address);

  if (Platform.OS === 'ios') {
    return `maps://?q=${query}`;
  }
  if (Platform.OS === 'android') {
    return `geo:0,0?q=${query}`;
  }
  return `https://www.google.com/maps/search/?api=1&query=${query}`;
}

type DetailRowProps = { label: string; value: string; isLast?: boolean };

function DetailRow({ label, value, isLast = false }: DetailRowProps) {
  return (
    <View style={[styles.detailRow, isLast && styles.detailRowLast]}>
      <Text style={styles.detailLabel}>{label}</Text>
      <Text style={styles.detailValue}>{value}</Text>
    </View>
  );
}

const styles = StyleSheet.create({
  /* Shared parent for the scrolling detail body and the overlay —
   * giving both a common positioned ancestor so the backdrop's
   * `position: absolute` reliably overlays the ScrollView sibling.
   * Without this they were implicit siblings of the Expo Router
   * screen wrapper, which on web gave the overlay a stacking
   * context that painted below the ScrollView content. */
  pageRoot: { flex: 1, position: 'relative' },
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

  /* Header back — explicit chevron + label so the control is always
   * visible, even for a cold deep-link entry where Expo Router's own
   * back arrow would be absent. Falls back to Home in `goBack`. */
  headerBack: {
    flexDirection: 'row',
    alignItems: 'center',
    paddingLeft: 4,
    paddingRight: 8,
    paddingVertical: 4,
  },
  headerBackLabel: {
    fontFamily: fonts.bodySemi,
    fontSize: 15,
    // White on the forest-green header. Matches the Ionicons tint.
    color: colors.onForest,
    marginLeft: -2,
  },

  /* Banner — poster shot above the HeroBand. The dark forest band
   * provides letterboxing when the poster isn't a 16:9 ratio; `contain`
   * ensures the whole poster is visible without crop. Tap opens the
   * full-screen viewer. Hidden when the API returns no banner_url
   * or when the image fails to load. */
  bannerFrame: {
    width: '100%',
    height: 220,
    backgroundColor: colors.forest,
  },
  banner: {
    width: '100%',
    height: '100%',
  },

  /* Full-screen poster viewer — dark backdrop + contain-fit image.
   * `position: absolute` with 0/0/0/0 insets fills the viewport on
   * both native (Modal already fills) and web (react-native-web
   * portals the Modal to document.body where `flex: 1` doesn't do
   * anything useful on its own). */
  viewerBackdrop: {
    // On web this is portaled to document.body (see PosterViewer), so
    // `position: fixed` gives us viewport pinning; on native this is
    // rendered inline above the ScrollView sibling, where RN treats
    // `fixed` as `absolute` and `elevation` keeps it above on Android.
    position: Platform.OS === 'web' ? ('fixed' as 'absolute') : 'absolute',
    top: 0,
    left: 0,
    right: 0,
    bottom: 0,
    backgroundColor: '#000',
    alignItems: 'center',
    justifyContent: 'center',
    padding: 24,
    zIndex: 10,
    elevation: 10,
  },
  viewerImage: {
    width: '100%',
    height: '100%',
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
   * last row explicitly drops its border (via the `isLast` prop on
   * `<DetailRow>`) so the card doesn't carry a dead hairline above
   * its bottom edge — the earlier `overflow: hidden` trick worked
   * cosmetically but still allocated the pixel. */
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
  detailRowLast: {
    borderBottomWidth: 0,
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
