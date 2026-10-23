import * as WebBrowser from 'expo-web-browser';
import { useCallback, useEffect, useMemo, useState } from 'react';
import {
  RefreshControl,
  ScrollView,
  SectionList,
  StyleSheet,
  Text,
  View,
} from 'react-native';

import { PROVINCES } from '@/api/provinces';
import { listVenues, Venue } from '@/api/venues';
import { colors, fonts } from '@/theme';
import { EmptyState, ErrorRow, FilterChip, RangeRow, Spinner } from '@/ui';

/**
 * M4h Find Ranges — list of published venues grouped by province.
 *
 * No distance line in v1 (user chose "no permission prompt"). Rows
 * open the website range page in an in-app browser so the shooter
 * sees photos, facilities and contact details without a round-trip
 * to Safari — matches the "Open match page" pattern used by the
 * match detail screen.
 */
export default function RangesList() {
  const [venues, setVenues] = useState<Venue[]>([]);
  const [loading, setLoading] = useState(true);
  const [refreshing, setRefreshing] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [province, setProvince] = useState<string | null>(null);

  const load = useCallback(async () => {
    setError(null);
    try {
      const rows = await listVenues({ province });
      setVenues(rows);
    } catch (err) {
      setError(
        err instanceof Error ? err.message : "Couldn't load ranges. Pull to retry.",
      );
    } finally {
      setLoading(false);
      setRefreshing(false);
    }
  }, [province]);

  useEffect(() => {
    setLoading(true);
    void load();
  }, [load]);

  const sections = useMemo(() => groupByProvince(venues), [venues]);

  const openRange = useCallback((venue: Venue) => {
    void WebBrowser.openBrowserAsync(venue.url);
  }, []);

  return (
    <View style={styles.container}>
      <View style={styles.filterRow}>
        <ScrollView
          horizontal
          showsHorizontalScrollIndicator={false}
          contentContainerStyle={styles.chipScroll}
        >
          <FilterChip
            label="Any province"
            selected={province === null}
            onPress={() => setProvince(null)}
          />
          {PROVINCES.map((p) => (
            <FilterChip
              key={p.slug}
              label={p.short}
              selected={province === p.slug}
              onPress={() => setProvince(province === p.slug ? null : p.slug)}
              accessibilityLabel={p.name}
            />
          ))}
        </ScrollView>
      </View>

      {loading && venues.length === 0 ? (
        <View style={styles.centre}>
          <Spinner label="Loading ranges" />
        </View>
      ) : error !== null && venues.length === 0 ? (
        <View style={styles.centre}>
          <ErrorRow>Couldn't load ranges. Pull to retry.</ErrorRow>
        </View>
      ) : venues.length === 0 ? (
        <ScrollView
          contentContainerStyle={styles.centre}
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
          <EmptyState icon="navigate-outline">
            {province === null
              ? 'No ranges listed yet. Pull to refresh.'
              : 'No ranges in that province yet. Try another one.'}
          </EmptyState>
        </ScrollView>
      ) : (
        <SectionList
          sections={sections}
          keyExtractor={(item) => String(item.id)}
          stickySectionHeadersEnabled
          contentContainerStyle={styles.listContent}
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
          renderSectionHeader={({ section }) => (
            <View style={styles.provinceHeaderWrap}>
              <Text style={styles.provinceHeader}>{section.title}</Text>
            </View>
          )}
          renderItem={({ item }) => (
            <RangeRow venue={item} onPress={() => openRange(item)} />
          )}
        />
      )}
    </View>
  );
}

/**
 * Group by province, falling back to "Elsewhere" for a province-less
 * venue. Sorted by province name so GP, KZN, WC et al. keep a stable
 * order between renders.
 */
function groupByProvince(venues: Venue[]): Array<{ title: string; data: Venue[] }> {
  const map = new Map<string, Venue[]>();

  for (const venue of venues) {
    const key = venue.province ?? 'Elsewhere';
    const bucket = map.get(key) ?? [];
    bucket.push(venue);
    map.set(key, bucket);
  }

  return Array.from(map.entries())
    .sort(([a], [b]) => a.localeCompare(b))
    .map(([title, data]) => ({ title, data }));
}

const styles = StyleSheet.create({
  container: { flex: 1, backgroundColor: colors.page },

  filterRow: {
    flexDirection: 'row',
    alignItems: 'center',
    backgroundColor: colors.card,
    borderBottomWidth: 1,
    borderBottomColor: colors.rule,
    paddingVertical: 8,
    // Keep this strip above the SectionList so sticky province
    // headers pin cleanly below it — same technique as Calendar's
    // filter block.
    zIndex: 10,
    elevation: 4,
  },
  chipScroll: {
    flexDirection: 'row',
    gap: 8,
    paddingHorizontal: 12,
    alignItems: 'center',
  },

  centre: {
    flex: 1,
    alignItems: 'center',
    justifyContent: 'center',
    paddingHorizontal: 16,
    paddingVertical: 32,
  },

  listContent: { paddingBottom: 24 },
  provinceHeaderWrap: {
    paddingHorizontal: 16,
    paddingTop: 10,
    paddingBottom: 8,
    backgroundColor: colors.page,
  },
  provinceHeader: {
    fontFamily: fonts.monoSemi,
    fontSize: 11,
    letterSpacing: 1.6,
    textTransform: 'uppercase',
    color: colors.link,
  },
});
