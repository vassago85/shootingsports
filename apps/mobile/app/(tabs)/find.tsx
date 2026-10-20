import { useRouter } from 'expo-router';
import { useEffect, useState } from 'react';
import { ActivityIndicator, StyleSheet, Text, TouchableOpacity, View } from 'react-native';
import MapView, { Marker, PROVIDER_GOOGLE, Region } from 'react-native-maps';

import { fetchMap, MapPayload } from '@/api/map';

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
            <ActivityIndicator />
          </View>
        ) : (
          <View style={styles.mapWrap}>
            <MapView
              style={StyleSheet.absoluteFill}
              provider={PROVIDER_GOOGLE}
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
            {MODES.find((m) => m.value === mode)?.label} search opens here when its API
            endpoint lands. For now, browse them on the website.
          </Text>
        </View>
      )}
    </View>
  );
}

const styles = StyleSheet.create({
  container: { flex: 1, backgroundColor: '#101516' },
  chipBar: {
    flexDirection: 'row',
    flexWrap: 'wrap',
    gap: 8,
    padding: 12,
    backgroundColor: '#161c1e',
  },
  chip: {
    paddingHorizontal: 12,
    paddingVertical: 6,
    borderRadius: 999,
    borderWidth: 1,
    borderColor: '#3a4548',
  },
  chipActive: { backgroundColor: '#d9ae52', borderColor: '#d9ae52' },
  chipText: { color: '#f4f0e6', fontSize: 12 },
  chipTextActive: { color: '#232a2c', fontWeight: '700' },
  mapWrap: { flex: 1 },
  center: { flex: 1, alignItems: 'center', justifyContent: 'center', backgroundColor: '#101516', padding: 24 },
  muted: { color: '#a3adaa', fontSize: 14, textAlign: 'center' },
  footer: {
    position: 'absolute',
    left: 0,
    right: 0,
    bottom: 0,
    padding: 12,
    backgroundColor: '#101516cc',
  },
  footerText: { color: '#f4f0e6', textAlign: 'center', fontSize: 12 },
  error: { color: '#f28c8c', textAlign: 'center' },
});
