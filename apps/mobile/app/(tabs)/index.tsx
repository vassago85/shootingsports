import { Link, useLocalSearchParams } from 'expo-router';
import { useCallback, useEffect, useState } from 'react';
import { ActivityIndicator, FlatList, RefreshControl, StyleSheet, Text, TouchableOpacity, View } from 'react-native';

import { EventCard, listEvents } from '@/api/events';

type Filters = {
  family: string | null;
  novice: boolean;
};

const FAMILIES: Array<{ value: string | null; label: string }> = [
  { value: null, label: 'All' },
  { value: 'rifle', label: 'Rifle' },
  { value: 'handgun', label: 'Handgun' },
  { value: 'shotgun', label: 'Shotgun' },
];

export default function CalendarScreen() {
  const params = useLocalSearchParams<{ province?: string }>();
  const [events, setEvents] = useState<EventCard[]>([]);
  const [loading, setLoading] = useState(true);
  const [refreshing, setRefreshing] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [filters, setFilters] = useState<Filters>({ family: null, novice: false });

  const load = useCallback(async () => {
    setError(null);
    try {
      const { events: rows } = await listEvents({
        family: filters.family,
        novice: filters.novice,
        province: params.province ?? null,
      });
      setEvents(rows);
    } catch (err) {
      setError(err instanceof Error ? err.message : 'Could not load matches.');
    } finally {
      setLoading(false);
      setRefreshing(false);
    }
  }, [filters, params.province]);

  useEffect(() => {
    setLoading(true);
    void load();
  }, [load]);

  return (
    <View style={styles.container}>
      <View style={styles.filterBar}>
        {FAMILIES.map((option) => {
          const active = filters.family === option.value;
          return (
            <TouchableOpacity
              key={option.label}
              onPress={() => setFilters((prev) => ({ ...prev, family: option.value }))}
              style={[styles.chip, active && styles.chipActive]}
            >
              <Text style={[styles.chipText, active && styles.chipTextActive]}>{option.label}</Text>
            </TouchableOpacity>
          );
        })}
        <TouchableOpacity
          onPress={() => setFilters((prev) => ({ ...prev, novice: !prev.novice }))}
          style={[styles.chip, filters.novice && styles.chipActive]}
        >
          <Text style={[styles.chipText, filters.novice && styles.chipTextActive]}>Novice</Text>
        </TouchableOpacity>
      </View>

      {loading ? (
        <ActivityIndicator style={{ marginTop: 32 }} />
      ) : error !== null ? (
        <Text style={styles.error}>{error}</Text>
      ) : (
        <FlatList
          data={events}
          keyExtractor={(item) => String(item.id)}
          contentContainerStyle={{ padding: 12, gap: 12 }}
          refreshControl={
            <RefreshControl
              refreshing={refreshing}
              onRefresh={() => {
                setRefreshing(true);
                void load();
              }}
            />
          }
          ListEmptyComponent={<Text style={styles.empty}>No matches match your filters.</Text>}
          renderItem={({ item }) => (
            <Link href={{ pathname: '/matches/[slug]', params: { slug: item.slug } }} asChild>
              <TouchableOpacity style={styles.card}>
                <Text style={styles.cardTitle}>{item.title}</Text>
                <Text style={styles.cardMeta}>
                  {item.starts_at !== null ? new Date(item.starts_at).toDateString() : 'TBC'}
                </Text>
                <Text style={styles.cardMeta}>{item.location_label}</Text>
                <Text style={styles.cardMeta}>{item.host.name}</Text>
              </TouchableOpacity>
            </Link>
          )}
        />
      )}
    </View>
  );
}

const styles = StyleSheet.create({
  container: { flex: 1, backgroundColor: '#101516' },
  filterBar: {
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
  card: {
    backgroundColor: '#1c2426',
    borderRadius: 8,
    padding: 14,
    gap: 4,
  },
  cardTitle: { color: '#f4f0e6', fontSize: 16, fontWeight: '600' },
  cardMeta: { color: '#a3adaa', fontSize: 12 },
  empty: { color: '#a3adaa', textAlign: 'center', marginTop: 48 },
  error: { color: '#f28c8c', textAlign: 'center', margin: 24 },
});
