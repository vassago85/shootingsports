import * as Linking from 'expo-linking';
import { Link, useFocusEffect, useRouter } from 'expo-router';
import { useCallback, useState } from 'react';
import {
  ActivityIndicator,
  Alert,
  RefreshControl,
  ScrollView,
  StyleSheet,
  Text,
  TouchableOpacity,
  View,
} from 'react-native';

import { logout } from '@/api/auth';
import { EventCard } from '@/api/events';
import { CapabilityCard, fetchCapabilities, fetchMe, MeProfile } from '@/api/me';
import { listSavedEvents, unsaveEvent } from '@/api/saved-events';
import { getAuthToken } from '@/auth/store';
import { colors } from '@/theme';

/**
 * Me — the shooter's home inside the app. Four blocks:
 *   1. Plan state (Free / Pro / Trial) from `/api/v1/me`.
 *   2. Manage hub (capability cards) when the shooter also wears a
 *      club / shop / range / media hat. Light actions only; the
 *      heavy editor (members, invoices, magazine, billing) opens
 *      on the website via the system browser.
 *   3. Saved matches, mirroring the web /my-calendar.
 *   4. Logout.
 *
 * No in-app Pro purchase. Pro is sold on the website so the Apple /
 * Google in-app purchase rules do not apply to this app; locked
 * rows direct the user to shootingsports.co.za to upgrade.
 *
 * Reload policy: {@link useFocusEffect} re-runs the loader every time
 * the tab regains focus. Save a match on Calendar, come back to Me,
 * and the saved list is already fresh — no pull-to-refresh needed.
 *
 * Covers eight visual states (see
 * `docs/superpowers/specs/2026-10-20-app-and-web-mockup-framework.md`
 * M5a–M5h): signed-out, Free + no caps, Free + 1 cap, Pro,
 * Trial, all four caps, loading, and error.
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

function openWebsite(url: string): void {
  Linking.openURL(url).catch(() => {
    Alert.alert('Could not open', 'Try opening shootingsports.co.za in your browser.');
  });
}

export default function MeScreen() {
  const router = useRouter();
  const [signedIn, setSignedIn] = useState<boolean | null>(null);
  const [me, setMe] = useState<MeProfile | null>(null);
  const [cards, setCards] = useState<CapabilityCard[]>([]);
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
      setError(err instanceof Error ? err.message : 'Could not load your profile.');
    } finally {
      setLoading(false);
      setRefreshing(false);
    }
  }, []);

  // Re-run the loader on every tab focus so saved matches, plan state
  // and capability cards stay in sync with anything the user did on
  // the Calendar / match-detail screens or on the website.
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

  // M5a — signed-out state. Covers both the pre-login and post-logout cases.
  if (signedIn === false) {
    return (
      <View style={styles.center}>
        <Text style={styles.eyebrowCenter}>My home</Text>
        <Text style={styles.copy}>
          Log in to see your home, save matches, and manage your clubs or business.
        </Text>
        <TouchableOpacity onPress={() => router.push('/login')} style={styles.primaryBtn}>
          <Text style={styles.primaryBtnText}>Log in</Text>
        </TouchableOpacity>
      </View>
    );
  }

  // M5g — first-load loading state.
  if (loading) {
    return (
      <View style={styles.center}>
        <ActivityIndicator color={colors.link} />
      </View>
    );
  }

  // M5h — hard error (first load failed, nothing cached). Full-screen retry.
  if (error !== null && me === null) {
    return (
      <View style={styles.center}>
        <Text style={styles.eyebrowCenter}>My home</Text>
        <Text style={styles.errorHeading}>Could not reach the server</Text>
        <Text style={styles.copyMuted}>{error}</Text>
        <TouchableOpacity
          onPress={() => {
            setLoading(true);
            void load();
          }}
          style={styles.primaryBtn}
        >
          <Text style={styles.primaryBtnText}>Try again</Text>
        </TouchableOpacity>
      </View>
    );
  }

  const plan = derivePlan(me);
  const planLabel = plan === 'trial'
    ? `Trial · ${me?.trial_days_remaining ?? 0} days left`
    : plan === 'pro'
      ? 'Pro'
      : 'Free';

  const planPillStyle = plan === 'free' ? styles.planPillFree : styles.planPillAccent;
  const planPillTextStyle = plan === 'free' ? styles.planPillTextFree : styles.planPillTextAccent;

  return (
    <ScrollView
      style={styles.container}
      contentContainerStyle={{ padding: 16, gap: 20 }}
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
      {/* Soft-error banner above the previously-loaded content. */}
      {error !== null && me !== null && (
        <View style={styles.errorBanner}>
          <Text style={styles.errorBannerText}>Could not refresh. Pull down to try again.</Text>
        </View>
      )}

      {me !== null && (
        <View>
          <Text style={styles.eyebrow}>My home</Text>
          <Text style={styles.h1}>{me.name}</Text>
          <View style={planPillStyle}>
            <Text style={planPillTextStyle}>{planLabel}</Text>
          </View>
        </View>
      )}

      {/* M5c–M5f — Manage hub, only when the shooter also wears a club / shop / range / media hat. */}
      {cards.length > 0 && (
        <View style={{ gap: 10 }}>
          <Text style={styles.sectionTitle}>Manage</Text>
          <Text style={styles.muted}>
            Light actions live here. Members, invoices, magazine, and billing open on the website.
          </Text>
          {cards.map((card) => (
            <View key={card.kind} style={styles.card}>
              <Text style={styles.cardEyebrow}>{card.kind}</Text>
              <Text style={styles.cardTitle}>{card.title}</Text>
              <Text style={styles.cardMeta}>{card.summary}</Text>
              {card.items.slice(0, 3).map((item) => (
                <Text key={item.name} style={styles.cardListItem}>· {item.name}</Text>
              ))}
              <TouchableOpacity
                style={styles.cardAction}
                onPress={() => openWebsite(card.primary_action.url)}
              >
                <Text style={styles.cardActionText}>{card.primary_action.label} ↗</Text>
              </TouchableOpacity>
            </View>
          ))}
        </View>
      )}

      {/* Saved matches. Mirrors the web /my-calendar. */}
      <View style={{ gap: 10 }}>
        <Text style={styles.sectionTitle}>Saved matches</Text>
        {saved.length === 0 ? (
          <Text style={styles.muted}>
            Nothing saved yet. Open the calendar and tap Save on a match.
          </Text>
        ) : (
          saved.map((event) => (
            <View key={event.id} style={styles.card}>
              <Link href={{ pathname: '/matches/[slug]', params: { slug: event.slug } }} asChild>
                <TouchableOpacity>
                  <Text style={styles.cardTitle}>{event.title}</Text>
                  <Text style={styles.cardMeta}>
                    {event.starts_at !== null ? new Date(event.starts_at).toDateString() : 'TBC'} · {event.location_label}
                  </Text>
                </TouchableOpacity>
              </Link>
              <TouchableOpacity
                onPress={async () => {
                  await unsaveEvent(event.id);
                  await load();
                }}
                style={styles.removeRow}
              >
                <Text style={styles.removeText}>Remove</Text>
              </TouchableOpacity>
            </View>
          ))
        )}
      </View>

      {/* Pro upsell. Opens the website — never charges inside the app. Hidden on Pro and Trial. */}
      {plan === 'free' && (
        <View style={styles.proCard}>
          <Text style={styles.proTitle}>Pro is managed on the website</Text>
          <Text style={styles.proBody}>
            Unlimited follows, unlimited log history, and dedicated-status export. Open
            shootingsports.co.za to see the current plans.
          </Text>
          <TouchableOpacity
            style={styles.primaryBtn}
            onPress={() => openWebsite('https://shootingsports.co.za/upgrade')}
          >
            <Text style={styles.primaryBtnText}>Open Pro on the website ↗</Text>
          </TouchableOpacity>
        </View>
      )}

      <TouchableOpacity
        onPress={async () => {
          // logout() handles its own errors and always clears the
          // local token, so the state transition below is safe.
          await logout();
          setSignedIn(false);
          setMe(null);
          setCards([]);
          setSaved([]);
          setError(null);
        }}
        style={styles.logoutRow}
      >
        <Text style={styles.logoutText}>Log out</Text>
      </TouchableOpacity>
    </ScrollView>
  );
}

// TEMPORARY stylesheet — stays until the full M5a–M5h reskin lands
// with the proper HeroBand, PlanPill, CapabilityCard and MatchCard
// shared components (Phase 1, docs/build-pack/BUILD_SPEC.md §3.2).
// All token references are the new palette's semantic matches: light
// surfaces, link-green accents, lime primaries, muted supporting text.
const styles = StyleSheet.create({
  container: { flex: 1, backgroundColor: colors.page },
  center: {
    flex: 1,
    alignItems: 'center',
    justifyContent: 'center',
    backgroundColor: colors.page,
    padding: 24,
    gap: 12,
  },

  /* Shared copy + buttons */
  copy: { color: colors.ink, fontSize: 16, textAlign: 'center' },
  copyMuted: { color: colors.muted, fontSize: 14, textAlign: 'center' },
  primaryBtn: {
    backgroundColor: colors.lime,
    paddingHorizontal: 20,
    paddingVertical: 12,
    borderRadius: 6,
    alignSelf: 'center',
  },
  primaryBtnText: {
    color: colors.ink,
    fontWeight: '700',
    fontSize: 14,
    letterSpacing: 0.5,
  },

  /* Hero */
  eyebrow: { color: colors.link, fontSize: 12, letterSpacing: 1.1, textTransform: 'uppercase' },
  eyebrowCenter: {
    color: colors.link,
    fontSize: 12,
    letterSpacing: 1.1,
    textTransform: 'uppercase',
    textAlign: 'center',
  },
  h1: { color: colors.ink, fontSize: 24, fontWeight: '700', marginTop: 4 },

  /* Plan pill — link-green border for Pro / Trial, grey for Free */
  planPillFree: {
    alignSelf: 'flex-start',
    marginTop: 10,
    paddingHorizontal: 10,
    paddingVertical: 4,
    borderRadius: 999,
    borderWidth: 1,
    borderColor: colors.rule,
  },
  planPillAccent: {
    alignSelf: 'flex-start',
    marginTop: 10,
    paddingHorizontal: 10,
    paddingVertical: 4,
    borderRadius: 999,
    borderWidth: 1,
    borderColor: colors.link,
    backgroundColor: colors.tint,
  },
  planPillTextFree: { color: colors.ink, fontSize: 12 },
  planPillTextAccent: { color: colors.link, fontSize: 12, fontWeight: '600' },

  /* Section titles + muted body copy */
  sectionTitle: { color: colors.ink, fontSize: 18, fontWeight: '600' },
  muted: { color: colors.muted, fontSize: 13 },

  /* Capability + match + saved cards share the same shape */
  card: {
    backgroundColor: colors.card,
    borderRadius: 8,
    padding: 14,
    gap: 4,
    borderWidth: 1,
    borderColor: colors.rule,
  },
  cardEyebrow: {
    color: colors.link,
    fontSize: 10,
    letterSpacing: 1.1,
    textTransform: 'uppercase',
  },
  cardTitle: { color: colors.ink, fontSize: 15, fontWeight: '600' },
  cardMeta: { color: colors.muted, fontSize: 12 },
  cardListItem: { color: colors.ink, fontSize: 13 },

  /* Capability-card primary action — lime fill so the website handoff
   * is the loudest thing on the card. Secondary / light in-app
   * actions can sit above or below this later. */
  cardAction: {
    marginTop: 10,
    paddingVertical: 10,
    paddingHorizontal: 14,
    backgroundColor: colors.lime,
    borderRadius: 6,
    alignSelf: 'flex-start',
  },
  cardActionText: { color: colors.ink, fontWeight: '700', fontSize: 13 },

  /* Remove-from-saved pill */
  removeRow: {
    marginTop: 8,
    paddingHorizontal: 10,
    paddingVertical: 6,
    borderRadius: 4,
    borderWidth: 1,
    borderColor: colors.rule,
    alignSelf: 'flex-start',
  },
  removeText: { color: colors.ink, fontSize: 12 },

  /* Pro upsell — forest card per spec M5 */
  proCard: {
    backgroundColor: colors.forest,
    borderRadius: 8,
    padding: 16,
    gap: 10,
  },
  proTitle: { color: colors.onForest, fontSize: 16, fontWeight: '700' },
  proBody: { color: colors.onForestMuted, fontSize: 13 },

  /* Error states */
  errorHeading: { color: colors.error, fontSize: 20, fontWeight: '700', textAlign: 'center' },
  errorBanner: {
    backgroundColor: colors.errorBg,
    borderWidth: 1,
    borderColor: colors.errorBorder,
    borderRadius: 6,
    paddingHorizontal: 12,
    paddingVertical: 10,
  },
  errorBannerText: { color: colors.error, fontSize: 13 },

  /* Logout */
  logoutRow: { padding: 16, alignItems: 'center' },
  logoutText: { color: colors.muted, fontSize: 13 },
});
