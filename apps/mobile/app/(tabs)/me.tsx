import { useFocusEffect, useRouter } from 'expo-router';
import * as WebBrowser from 'expo-web-browser';
import { useCallback, useState } from 'react';
import { Alert, RefreshControl, ScrollView, StyleSheet, Text, View } from 'react-native';

import { logout } from '@/api/auth';
import { EventCard } from '@/api/events';
import { CapabilityCard as CapabilityCardPayload, fetchCapabilities, fetchMe, MeProfile } from '@/api/me';
import { listSavedEvents, unsaveEvent } from '@/api/saved-events';
import { getAuthToken } from '@/auth/store';
import { colors, fonts } from '@/theme';
import {
  CapabilityCard,
  EmptyState,
  ErrorRow,
  GhostButton,
  HeroBand,
  MatchCard,
  PlanPill,
  PrimaryButton,
  SectionTitle,
  Spinner,
} from '@/ui';

/**
 * M5 Me — the shooter's home inside the app.
 *
 * Section order per BUILD_SPEC §3.2:
 *   HeroBand (My home / name / PlanPill)
 *   Manage          — only when `has_desk_access` + cards exist
 *   Saved matches   — always
 *   Pro upsell      — Free plan only (never Trial, never Pro)
 *   Log out         — subtle, ghost
 *
 * Covers M5a–M5h:
 *   M5a Signed-out  M5b Free + 0 caps  M5c Free + 1 cap  M5d Pro
 *   M5e Trial       M5f Free + 4 caps  M5g Loading       M5h Error
 *
 * Reload policy: {@link useFocusEffect} re-runs on every tab focus so
 * saving a match on Calendar makes the saved list fresh here without
 * pull-to-refresh.
 *
 * No in-app Pro purchase. Pro is sold on the website so Apple / Google
 * IAP rules do not apply to this app; the upsell + capability actions
 * open in an in-app browser (SFSafariViewController on iOS, Custom Tabs
 * on Android) via `expo-web-browser`.
 *
 * Spec open questions (§8) still unresolved and reflected in copy:
 *   - §8.1 CapabilityCard item contents  → rendered generically from payload
 *   - §8.2 Pro price + Free limits lifted → upsell copy stays generic
 *   - §8.5 Desk hand-off session carry   → opens in in-app browser; user may
 *                                          need to sign in on first open
 */

type Plan = 'free' | 'pro' | 'trial';

function derivePlan(me: MeProfile | null): Plan {
  if (me === null) {
    return 'free';
  }

  if (me.on_trial) {
    return 'trial';
  }

  return me.is_pro ? 'pro' : 'free';
}

async function openInAppBrowser(url: string): Promise<void> {
  try {
    await WebBrowser.openBrowserAsync(url);
  } catch {
    Alert.alert('Could not open', 'Try opening shootingsports.co.za in your browser.');
  }
}

export default function MeScreen() {
  const router = useRouter();
  const [signedIn, setSignedIn] = useState<boolean | null>(null);
  const [me, setMe] = useState<MeProfile | null>(null);
  const [cards, setCards] = useState<CapabilityCardPayload[]>([]);
  const [saved, setSaved] = useState<EventCard[]>([]);
  const [loading, setLoading] = useState(true);
  const [refreshing, setRefreshing] = useState(false);
  const [error, setError] = useState<string | null>(null);

  const load = useCallback(async () => {
    setError(null);
    try {
      const profile = await fetchMe();
      setMe(profile);

      if (profile.has_desk_access) {
        setCards(await fetchCapabilities());
      } else {
        setCards([]);
      }

      setSaved(await listSavedEvents());
    } catch (err) {
      setError(err instanceof Error ? err.message : 'Could not load your home.');
    } finally {
      setLoading(false);
      setRefreshing(false);
    }
  }, []);

  useFocusEffect(
    useCallback(() => {
      let cancelled = false;

      void (async () => {
        const token = await getAuthToken();
        if (cancelled) {
          return;
        }

        const hasToken = token !== null;
        setSignedIn(hasToken);

        if (hasToken) {
          await load();
        } else {
          setLoading(false);
        }
      })();

      return () => {
        cancelled = true;
      };
    }, [load]),
  );

  const openMatch = useCallback(
    (slug: string) =>
      router.push({ pathname: '/matches/[slug]', params: { slug, from: 'me' } }),
    [router],
  );

  // M5a — Signed out. Centred icon + copy + Sign in button. No HeroBand
  // because there's no user data yet, and no tab-bar-only focus so the
  // user can still browse Home/Calendar/Find anonymously.
  if (signedIn === false) {
    return (
      <View style={styles.centerStage}>
        <EmptyState icon="person-circle-outline">
          Sign in to see your home, save matches, and manage your clubs or business.
        </EmptyState>
        <View style={styles.stageAction}>
          <PrimaryButton label="Sign in" onPress={() => router.push('/login')} />
        </View>
      </View>
    );
  }

  // M5g — First-load loading. Full-screen spinner (tab bar stays visible
  // because it lives above this screen).
  if (loading && me === null) {
    return (
      <View style={styles.centerStage}>
        <Spinner label="Loading your home" />
      </View>
    );
  }

  // M5h (hard variant) — First load failed, nothing cached. Full-screen
  // error + retry. The soft variant (error banner above cached content)
  // is handled below the HeroBand.
  if (error !== null && me === null) {
    return (
      <View style={styles.centerStage}>
        <ErrorRow>Couldn't load your home. Pull to retry, or try again.</ErrorRow>
        <View style={styles.stageAction}>
          <GhostButton
            label="Try again"
            onPress={() => {
              setLoading(true);
              void load();
            }}
          />
        </View>
      </View>
    );
  }

  const plan = derivePlan(me);

  return (
    <ScrollView
      style={styles.container}
      refreshControl={
        <RefreshControl
          tintColor={colors.link}
          refreshing={refreshing}
          onRefresh={() => {
            setRefreshing(true);
            void load();
          }}
        />
      }
    >
      {me !== null && (
        <HeroBand
          eyebrow="My home"
          title={me.name}
          pill={
            <PlanPill
              plan={plan}
              trialDaysRemaining={me.trial_days_remaining}
              onForest
            />
          }
        />
      )}

      <View style={styles.body}>
        {/* M5h soft variant — refresh failed but cached data is still shown. */}
        {error !== null && me !== null && (
          <ErrorRow>Couldn't refresh your home. Pull down to try again.</ErrorRow>
        )}

        {/* Manage — only when capabilities exist (M5c / M5f). Order
         * follows the spec: Club, Shop, Range, Media. Backend returns
         * them in that order so no client-side sort is needed. */}
        {cards.length > 0 && (
          <View style={styles.section}>
            <SectionTitle>Manage</SectionTitle>
            <Text style={styles.sectionLead}>
              Light actions live here. Members, invoices, magazine, and billing open on the website.
            </Text>
            <View style={styles.stack}>
              {cards.map((card) => (
                <CapabilityCard
                  key={card.kind}
                  card={card}
                  onPressAction={(url) => {
                    void openInAppBrowser(url);
                  }}
                />
              ))}
            </View>
          </View>
        )}

        {/* Saved matches — always shown. Empty state copy is distinct
         * from "feature not available" per spec §7. */}
        <View style={styles.section}>
          <SectionTitle>Saved matches</SectionTitle>
          {saved.length === 0 ? (
            <EmptyState icon="bookmark-outline">
              Nothing saved yet. Open the calendar and save a match.
            </EmptyState>
          ) : (
            <View style={styles.stack}>
              {saved.map((event) => (
                <View key={event.id} style={styles.savedWrap}>
                  <MatchCard event={event} onPress={() => openMatch(event.slug)} />
                  <GhostButton
                    label="Remove from saved"
                    onPress={async () => {
                      await unsaveEvent(event.id);
                      await load();
                    }}
                  />
                </View>
              ))}
            </View>
          )}
        </View>

        {/* Pro upsell — Free only. Opens the website in in-app browser.
         * Copy stays generic: price + lifted-limits list are open
         * questions (spec §8.2) and must not be hard-coded. */}
        {plan === 'free' && (
          <View style={styles.proCard}>
            <Text style={styles.proEyebrow}>Pro</Text>
            <Text style={styles.proTitle}>Go Pro</Text>
            <Text style={styles.proBody}>
              Pro lifts the Free plan limits. Open shootingsports.co.za to see the current plans.
            </Text>
            <PrimaryButton
              label="Open Pro on the website"
              onPress={() => {
                void openInAppBrowser('https://shootingsports.co.za/upgrade');
              }}
              style={styles.proAction}
            />
          </View>
        )}

        {/* Log out — subtle on purpose per spec. Clears SecureStore
         * (handled inside `logout()`) and resets local state to M5a. */}
        <View style={styles.logoutWrap}>
          <GhostButton
            label="Log out"
            onPress={async () => {
              await logout();
              setSignedIn(false);
              setMe(null);
              setCards([]);
              setSaved([]);
              setError(null);
            }}
          />
        </View>
      </View>
    </ScrollView>
  );
}

const styles = StyleSheet.create({
  container: { flex: 1, backgroundColor: colors.page },

  /* M5a / M5g / M5h share this centred stage. */
  centerStage: {
    flex: 1,
    alignItems: 'center',
    justifyContent: 'center',
    backgroundColor: colors.page,
    padding: 24,
    gap: 16,
  },
  stageAction: { width: '100%', maxWidth: 260 },

  /* Signed-in content below the HeroBand. */
  body: { padding: 16, gap: 24 },
  section: { gap: 12 },
  sectionLead: {
    fontFamily: fonts.body,
    fontSize: 14,
    lineHeight: 20,
    color: colors.muted,
  },
  stack: { gap: 12 },
  savedWrap: { gap: 8 },

  /* Pro upsell — forest card. Price and limits intentionally generic
   * (spec §8.2). */
  proCard: {
    backgroundColor: colors.forest,
    borderRadius: 14,
    padding: 18,
    gap: 10,
  },
  proEyebrow: {
    fontFamily: fonts.monoSemi,
    fontSize: 11,
    letterSpacing: 1.6,
    textTransform: 'uppercase',
    color: colors.lime,
  },
  proTitle: {
    fontFamily: fonts.display,
    fontSize: 26,
    textTransform: 'uppercase',
    letterSpacing: 0.5,
    color: colors.onForest,
  },
  proBody: {
    fontFamily: fonts.body,
    fontSize: 14,
    lineHeight: 20,
    color: colors.onForestMuted,
  },
  proAction: { alignSelf: 'flex-start', marginTop: 4 },

  /* Logout — small, centred ghost. */
  logoutWrap: { marginTop: 8, alignSelf: 'center' },
});
