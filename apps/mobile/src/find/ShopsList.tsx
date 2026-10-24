import * as WebBrowser from 'expo-web-browser';
import { useCallback, useEffect, useState } from 'react';
import {
  FlatList,
  RefreshControl,
  ScrollView,
  StyleSheet,
  Text,
  View,
} from 'react-native';

import { listProviders, PROVIDER_CATEGORIES, Provider } from '@/api/providers';
import { PROVINCES } from '@/api/provinces';
import { colors, fonts } from '@/theme';
import { EmptyState, ErrorRow, FilterChip, ShopRow, Spinner } from '@/ui';

/**
 * M4j Find Shops — compact single-row list of published suppliers,
 * matching the ClubRow/RangeRow pattern used elsewhere in Find.
 *
 * Two chip rows: province (optional) and category (always visible,
 * matches the ProviderCategory enum). Rows reset the list on
 * change. Tapping a row opens the website supplier page in an
 * in-app browser so the shooter sees photos, services, phone and
 * email without a Safari bounce.
 *
 * The earlier 2-column tile grid wasted vertical space on a
 * repetitive square logo region; the row layout fits roughly 7–8
 * shops in a viewport that only held 4 tiles.
 */
export default function ShopsList() {
  const [providers, setProviders] = useState<Provider[]>([]);
  const [loading, setLoading] = useState(true);
  const [refreshing, setRefreshing] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [province, setProvince] = useState<string | null>(null);
  const [category, setCategory] = useState<string | null>(null);

  const load = useCallback(async () => {
    setError(null);
    try {
      const rows = await listProviders({ province, category });
      setProviders(rows);
    } catch (err) {
      setError(
        err instanceof Error ? err.message : "Couldn't load shops. Pull to retry.",
      );
    } finally {
      setLoading(false);
      setRefreshing(false);
    }
  }, [province, category]);

  useEffect(() => {
    setLoading(true);
    void load();
  }, [load]);

  const openShop = useCallback((provider: Provider) => {
    void WebBrowser.openBrowserAsync(provider.url);
  }, []);

  return (
    <View style={styles.container}>
      <View style={styles.filterStack}>
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

        <View style={styles.filterRow}>
          <ScrollView
            horizontal
            showsHorizontalScrollIndicator={false}
            contentContainerStyle={styles.chipScroll}
          >
            <FilterChip
              label="All categories"
              selected={category === null}
              onPress={() => setCategory(null)}
            />
            {PROVIDER_CATEGORIES.map((c) => (
              <FilterChip
                key={c.slug}
                label={c.label}
                selected={category === c.slug}
                onPress={() => setCategory(category === c.slug ? null : c.slug)}
              />
            ))}
          </ScrollView>
        </View>
      </View>

      {loading && providers.length === 0 ? (
        <View style={styles.centre}>
          <Spinner label="Loading shops" />
        </View>
      ) : error !== null && providers.length === 0 ? (
        <View style={styles.centre}>
          <ErrorRow>Couldn't load shops. Pull to retry.</ErrorRow>
        </View>
      ) : providers.length === 0 ? (
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
          <EmptyState icon="storefront-outline">
            {province === null && category === null
              ? 'No shops listed yet. Pull to refresh.'
              : 'No shops match those filters. Try widening the search.'}
          </EmptyState>
        </ScrollView>
      ) : (
        <FlatList
          data={providers}
          keyExtractor={(item) => item.slug}
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
          renderItem={({ item }) => (
            <ShopRow provider={item} onPress={() => openShop(item)} />
          )}
          ListFooterComponent={
            <Text style={styles.footer}>Showing {providers.length} suppliers</Text>
          }
        />
      )}
    </View>
  );
}

const styles = StyleSheet.create({
  container: { flex: 1, backgroundColor: colors.page },

  filterStack: {
    backgroundColor: colors.card,
    borderBottomWidth: 1,
    borderBottomColor: colors.rule,
    paddingVertical: 4,
    zIndex: 10,
    elevation: 4,
  },
  filterRow: {
    flexDirection: 'row',
    alignItems: 'center',
    paddingVertical: 4,
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

  listContent: {
    paddingBottom: 24,
  },
  footer: {
    marginTop: 16,
    marginBottom: 24,
    textAlign: 'center',
    fontFamily: fonts.monoSemi,
    fontSize: 11,
    letterSpacing: 1.3,
    textTransform: 'uppercase',
    color: colors.muted,
  },
});
