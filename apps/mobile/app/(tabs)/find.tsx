import { Ionicons } from '@expo/vector-icons';
import { useRouter } from 'expo-router';
import { ComponentProps, useEffect, useState } from 'react';
import { Platform, ScrollView, StyleSheet, Text, View } from 'react-native';
import MapView, { Marker, PROVIDER_GOOGLE, Region } from 'react-native-maps';

import { fetchMap, MapPayload } from '@/api/map';
import { colors, fonts } from '@/theme';
import { EmptyState, ErrorRow, FilterChip, HeroBand, Spinner } from '@/ui';

type IoniconName = ComponentProps<typeof Ionicons>['name'];

/**
 * M4 Find — one surface for discovering events, clubs, ranges,
 * shops and sports. The map backs Events today; the other four modes
 * are placeholders that light up as their directory endpoints land.
 * The chip-switching UX is here from day one so repeat users never
 * have to re-learn where to look.
 *
 * Map provider strategy:
 *   - Android: Google Maps via PROVIDER_GOOGLE. The API key is baked
 *     into AndroidManifest.xml at prebuild time from app.json →
 *     android.config.googleMaps.apiKey. If a future fork ships without
 *     a key the MapView crashes natively on mount — the fix is to add
 *     the key to app.json, not to guard here.
 *   - iOS: Apple Maps (the default when `provider` is undefined). Free,
 *     no key, no billing. Enabling Google Maps on iOS requires extra
 *     pod setup (`react-native-google-maps` subspec) that the Expo
 *     managed build doesn't ship by default.
 *
 * States per BUILD_SPEC §3.2:
 *   M4a Events clusters    — map + pins + footer with upcoming count
 *   M4b Events pin tapped  — default marker callout + tap handoff to
 *                            Calendar filtered by the tapped venue
 *                            (not the whole province — the user
 *                            came for THIS range)
 *   M4c Events loading     — Spinner overlay on dimmed map
 *   M4d Events error       — ErrorRow overlay on dimmed map
 *   M4e/g/i/k Mode today   — per-mode EmptyState icon + "coming soon" copy
 *   M4f/h/j/l (future)     — not built; backlog items (spec §6 Phase 5)
 */

const mapProvider = Platform.OS === 'android' ? PROVIDER_GOOGLE : undefined;

const SOUTH_AFRICA_REGION: Region = {
  latitude: -29.0,
  longitude: 25.0,
  latitudeDelta: 12,
  longitudeDelta: 12,
};

type FindMode = 'events' | 'clubs' | 'ranges' | 'shops' | 'sports';

type ModeConfig = {
  value: FindMode;
  label: string;
  /** Ionicon shown inside the EmptyState for M4e/g/i/k. */
  icon: IoniconName;
};

const MODES: ReadonlyArray<ModeConfig> = [
  { value: 'events', label: 'Events', icon: 'map-outline' },
  { value: 'clubs', label: 'Clubs', icon: 'people-outline' },
  { value: 'ranges', label: 'Ranges', icon: 'navigate-outline' },
  { value: 'shops', label: 'Shops', icon: 'storefront-outline' },
  { value: 'sports', label: 'Sports', icon: 'trophy-outline' },
];

export default function FindScreen() {
  const router = useRouter();
  const [mode, setMode] = useState<FindMode>('events');
  const [payload, setPayload] = useState<MapPayload | null>(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

  useEffect(() => {
    if (mode !== 'events') {
      return;
    }

    void (async () => {
      setError(null);
      setLoading(true);
      try {
        setPayload(await fetchMap());
      } catch (err) {
        setError(err instanceof Error ? err.message : "Couldn't load matches for the map.");
      } finally {
        setLoading(false);
      }
    })();
  }, [mode]);

  return (
    <View style={styles.container}>
      <HeroBand eyebrow="Discover" title="Find" />

      {/* Mode chip strip — horizontally scrolling. */}
      <View style={styles.chipStrip}>
        <ScrollView
          horizontal
          showsHorizontalScrollIndicator={false}
          contentContainerStyle={styles.chipRow}
        >
          {MODES.map((option) => (
            <FilterChip
              key={option.value}
              label={option.label}
              selected={mode === option.value}
              onPress={() => setMode(option.value)}
            />
          ))}
        </ScrollView>
      </View>

      {mode === 'events' ? (
        <EventsMap
          payload={payload}
          loading={loading}
          error={error}
          onPinPress={(pin) =>
            router.push({
              pathname: '/calendar',
              // Pass venue first — that's what the user tapped. We
              // also pass the venue's display name + province so the
              // Calendar chip strip can render a labelled filter
              // without a second round-trip to look it up.
              params: {
                venue: pin.slug,
                venueName: pin.name,
                ...(pin.province_slug !== null ? { province: pin.province_slug } : {}),
              },
            })
          }
        />
      ) : (
        <ModePlaceholder config={MODES.find((m) => m.value === mode) ?? MODES[0]} />
      )}
    </View>
  );
}

type EventsMapProps = {
  payload: MapPayload | null;
  loading: boolean;
  error: string | null;
  onPinPress: (pin: MapPayload['pins'][number]) => void;
};

function EventsMap({ payload, loading, error, onPinPress }: EventsMapProps) {
  // M4d — error overlay with ErrorRow. Chips above stay interactive.
  if (error !== null && payload === null) {
    return (
      <View style={styles.messageArea}>
        <ErrorRow>Couldn't load matches for the map. Pull to retry.</ErrorRow>
      </View>
    );
  }

  // M4c — initial loading. Spinner above the dimmed map area.
  if (loading && payload === null) {
    return (
      <View style={styles.messageArea}>
        <Spinner label="Loading upcoming matches" />
      </View>
    );
  }

  if (payload === null) {
    return <View style={styles.mapWrap} />;
  }

  return (
    <View style={styles.mapWrap}>
      <MapView
        style={StyleSheet.absoluteFill}
        provider={mapProvider}
        initialRegion={SOUTH_AFRICA_REGION}
      >
        {payload.pins.map((pin) => (
          <Marker
            key={pin.slug}
            coordinate={{ latitude: pin.lat, longitude: pin.lng }}
            title={pin.name}
            description={`${pin.count} upcoming · tap for matches at this range`}
            onCalloutPress={() => onPinPress(pin)}
          />
        ))}
      </MapView>

      <View style={styles.footer} pointerEvents="none">
        <Text style={styles.footerText}>
          {payload.total_matches} upcoming match{payload.total_matches === 1 ? '' : 'es'}
          {' at '}
          {payload.pins.length} pinned range{payload.pins.length === 1 ? '' : 's'}
        </Text>
      </View>
    </View>
  );
}

function ModePlaceholder({ config }: { config: ModeConfig }) {
  return (
    <View style={styles.messageArea}>
      <EmptyState icon={config.icon}>
        {config.label} search is coming soon. For now, browse them on the website.
      </EmptyState>
    </View>
  );
}

const styles = StyleSheet.create({
  container: { flex: 1, backgroundColor: colors.page },

  /* Mode chip strip under the HeroBand. Mirrors Calendar's filter strip
   * styling so the two screens read as one visual language. */
  chipStrip: {
    backgroundColor: colors.card,
    borderBottomWidth: 1,
    borderBottomColor: colors.rule,
    paddingVertical: 10,
  },
  chipRow: {
    flexDirection: 'row',
    gap: 8,
    paddingHorizontal: 12,
    alignItems: 'center',
  },

  mapWrap: { flex: 1 },

  /* Centred stage used by loading / error / mode-placeholder states.
   * Pushes content away from the chip strip and from the tab bar. */
  messageArea: {
    flex: 1,
    alignItems: 'center',
    justifyContent: 'center',
    paddingHorizontal: 16,
    backgroundColor: colors.page,
  },

  /* M4a footer — forest band with upcoming-match count. Non-interactive
   * so the user can tap through to the markers beneath. */
  footer: {
    position: 'absolute',
    left: 0,
    right: 0,
    bottom: 0,
    paddingVertical: 10,
    paddingHorizontal: 16,
    backgroundColor: colors.forest,
  },
  footerText: {
    color: colors.onForest,
    textAlign: 'center',
    fontFamily: fonts.monoSemi,
    fontSize: 12,
    letterSpacing: 1.2,
    textTransform: 'uppercase',
  },
});
