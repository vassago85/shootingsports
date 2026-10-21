import { Link, useLocalSearchParams } from 'expo-router';
import { useCallback, useEffect, useState } from 'react';
import { ActivityIndicator, FlatList, RefreshControl, StyleSheet, Text, TouchableOpacity, View } from 'react-native';

import { EventCard, listEvents } from '@/api/events';
import { colors, fonts } from '@/theme';

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

/**
 * Calendar list — the information-dense repeat-use surface. Filters
 * stay at the top; the detail page is reached through the match link.
 */
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
        <ActivityIndicator style={{ marginTop: 32 }} color={colors.link} />
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

// TEMPORARY stylesheet — stays until the full M3 reskin lands
// (sticky chip strip, pinned Novice toggle, removable province
// chip, MatchRow grouped by month, states a-e per BUILD_SPEC §3.2).
const styles = StyleSheet.create({
  container: { flex: 1, backgroundColor: colors.page },
  filterBar: {
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
  card: {
    backgroundColor: colors.card,
    borderWidth: 1,
    borderColor: colors.rule,
    borderRadius: 14,
    padding: 16,
    gap: 6,
  },
  cardTitle: { color: colors.ink, fontSize: 16, fontWeight: '600' },
  cardMeta: { color: colors.muted, fontSize: 12 },
  empty: { color: colors.muted, textAlign: 'center', marginTop: 48 },
  error: { color: colors.error, textAlign: 'center', margin: 24 },
});
