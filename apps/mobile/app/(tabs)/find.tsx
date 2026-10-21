import { useRouter } from 'expo-router';
import { useEffect, useState } from 'react';
import { ActivityIndicator, Platform, StyleSheet, Text, TouchableOpacity, View } from 'react-native';
import MapView, { Marker, PROVIDER_GOOGLE, Region } from 'react-native-maps';

import { fetchMap, MapPayload } from '@/api/map';
import { colors, fonts } from '@/theme';

/**
 * Map provider strategy:
 *   - Android: Google Maps via PROVIDER_GOOGLE. The API key is baked
 *     into AndroidManifest.xml at prebuild time from app.json →
 *     android.config.googleMaps.apiKey. If a future fork ships without
 *     a key the MapView crashes natively on mount — the fix is to add
 *     the key to app.json, not to guard here (Constants.expoConfig
 *     doesn't expose android.config in production builds, so a runtime
 *     guard isn't possible).
 *   - iOS: Apple Maps (the default when `provider` is undefined). Free,
 *     no key, no billing. Enabling Google Maps on iOS requires extra
 *     pod setup (`react-native-google-maps` subspec) that the Expo
 *     managed build doesn't ship by default.
 */
const mapProvider = Platform.OS === 'android' ? PROVIDER_GOOGLE : undefined;

const SOUTH_AFRICA_REGION: Region = {
  latitude: -29.0,
  longitude: 25.0,
  latitudeDelta: 12,
  longitudeDelta: 12,
};

type FindMode = 'events' | 'clubs' | 'ranges' | 'shops' | 'sports';

const MODES: Array<{ value: FindMode; label: string }> = [
  { value: 'events', label: 'Events' },
  { value: 'clubs', label: 'Clubs' },
  { value: 'ranges', label: 'Ranges' },
  { value: 'shops', label: 'Shops' },
  { value: 'sports', label: 'Sports' },
];

/**
 * Find — one surface for discovering events, clubs, ranges, shops,
 * and sports. The map backs Events today; the chips for Clubs,
 * Ranges, Shops, and Sports are placeholders that will light up as
 * those directory endpoints land on the API. The chip-switching UX
 * is here from day one so repeat users never have to re-learn where
 * to look.
 */
export default function FindScreen() {
  const router = useRouter();
  const [mode, setMode] = useState<FindMode>('events');
  const [payload, setPayload] = useState<MapPayload | null>(null);
  const [error, setError] = useState<string | null>(null);

  useEffect(() => {
    if (mode !== 'events') {
      return;
    }

    void (async () => {
      try {
        setPayload(await fetchMap());
      } catch (err) {
        setError(err instanceof Error ? err.message : 'Could not load the map.');
      }
    })();
  }, [mode]);

  return (
    <View style={styles.container}>
      <View style={styles.chipBar}>
        {MODES.map((option) => {
          const active = mode === option.value;
          return (
            <TouchableOpacity
              key={option.value}
              onPress={() => setMode(option.value)}
              style={[styles.chip, active && styles.chipActive]}
            >
              <Text style={[styles.chipText, active && styles.chipTextActive]}>{option.label}</Text>
            </TouchableOpacity>
          );
        })}
      </View>

      {mode === 'events' ? (
        error !== null ? (
          <View style={styles.center}>
            <Text style={styles.error}>{error}</Text>
          </View>
        ) : payload === null ? (
          <View style={styles.center}>
            <ActivityIndicator color={colors.link} />
          </View>
        ) : (
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
                  description={`${pin.count} upcoming`}
                  onCalloutPress={() => {
                    if (pin.province_slug !== null) {
                      router.push({ pathname: '/calendar', params: { province: pin.province_slug } });
                    }
                  }}
                />
              ))}
            </MapView>

            <View style={styles.footer} pointerEvents="none">
              <Text style={styles.footerText}>
                {payload.total_matches} upcoming matches at {payload.pins.length} pinned ranges
              </Text>
            </View>
          </View>
        )
      ) : (
        <View style={styles.center}>
          <Text style={styles.muted}>
            {MODES.find((m) => m.value === mode)?.label} search is coming soon.
            For now, browse them on the website.
          </Text>
        </View>
      )}
    </View>
  );
}

// TEMPORARY stylesheet — stays until the full M4 reskin lands
// (chip strip for Events/Clubs/Ranges/Shops/Sports, states a-l,
// placeholders say "opens here when its API endpoint lands"
// per BUILD_SPEC §3.2). Palette matches the pack's forest/lime.
const styles = StyleSheet.create({
  container: { flex: 1, backgroundColor: colors.page },
  chipBar: {
    flexDirection: 'row',
    flexWrap: 'wrap',
    gap: 8,
    padding: 12,
    backgroundColor: colors.card,
    borderBottomWidth: 1,
    borderBottomColor: colors.rule,
  },
  chip: {
    paddingHorizontal: 14,
    paddingVertical: 10,
    borderRadius: 999,
    borderWidth: 1,
    borderColor: colors.chipBorder,
    backgroundColor: colors.card,
  },
  chipActive: { backgroundColor: colors.forest, borderColor: colors.forest },
  chipText: { color: colors.ink, fontSize: 14, fontFamily: fonts.bodyMedium },
  chipTextActive: { color: colors.page, fontWeight: '700' },
  mapWrap: { flex: 1 },
  center: {
    flex: 1,
    alignItems: 'center',
    justifyContent: 'center',
    backgroundColor: colors.page,
    padding: 24,
  },
  muted: { color: colors.muted, fontSize: 15, textAlign: 'center', lineHeight: 22 },
  footer: {
    position: 'absolute',
    left: 0,
    right: 0,
    bottom: 0,
    padding: 12,
    backgroundColor: colors.forest,
  },
  footerText: { color: colors.onForest, textAlign: 'center', fontSize: 12 },
  error: { color: colors.error, textAlign: 'center' },
});
