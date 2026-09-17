import { useRouter } from 'expo-router';
import { useEffect, useState } from 'react';
import { ActivityIndicator, StyleSheet, Text, View } from 'react-native';
import MapView, { Marker, PROVIDER_GOOGLE, Region } from 'react-native-maps';

import { fetchMap, MapPayload } from '@/api/map';

const SOUTH_AFRICA_REGION: Region = {
  latitude: -29.0,
  longitude: 25.0,
  latitudeDelta: 12,
  longitudeDelta: 12,
};

export default function MapScreen() {
  const router = useRouter();
  const [payload, setPayload] = useState<MapPayload | null>(null);
  const [error, setError] = useState<string | null>(null);

  useEffect(() => {
    void (async () => {
      try {
        setPayload(await fetchMap());
      } catch (err) {
        setError(err instanceof Error ? err.message : 'Could not load the map.');
      }
    })();
  }, []);

  if (error !== null) {
    return (
      <View style={styles.center}>
        <Text style={styles.error}>{error}</Text>
      </View>
    );
  }

  if (payload === null) {
    return (
      <View style={styles.center}>
        <ActivityIndicator />
      </View>
    );
  }

  return (
    <View style={styles.container}>
      <MapView
        style={StyleSheet.absoluteFillObject}
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
                router.push({ pathname: '/', params: { province: pin.province_slug } });
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
  );
}

const styles = StyleSheet.create({
  container: { flex: 1, backgroundColor: '#101516' },
  center: { flex: 1, alignItems: 'center', justifyContent: 'center', backgroundColor: '#101516' },
  footer: {
    position: 'absolute',
    left: 0,
    right: 0,
    bottom: 0,
    padding: 12,
    backgroundColor: '#101516cc',
  },
  footerText: { color: '#f4f0e6', textAlign: 'center', fontSize: 12 },
  error: { color: '#f28c8c', textAlign: 'center', margin: 24 },
});
