import * as WebBrowser from 'expo-web-browser';
import { useCallback, useEffect, useMemo, useState } from 'react';
import {
  RefreshControl,
  ScrollView,
  StyleSheet,
  Text,
  View,
} from 'react-native';

import { listProviders, PROVIDER_CATEGORIES, Provider } from '@/api/providers';
import { PROVINCES } from '@/api/provinces';
import { colors, fonts } from '@/theme';
import { EmptyState, ErrorRow, FilterChip, ShopTile, Spinner } from '@/ui';

/**
 * M4j Find Shops — 2-column tile grid of published suppliers.
 *
 * Two chip rows: province (optional) and category (always visible,
 * matches the ProviderCategory enum). Rows reset the list on
 * change. Tapping a tile opens the website supplier page in an
 * in-app browser so the shooter sees photos, services, phone and
 * email without a Safari bounce.
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

  const rows = useMemo(() => chunkIntoPairs(providers), [providers]);

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
        <ScrollView
          contentContainerStyle={styles.gridContent}
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
          {rows.map((pair, idx) => (
            <View key={`row-${idx}`} style={styles.gridRow}>
              {pair.map((provider) => (
                <ShopTile
                  key={provider.slug}
                  provider={provider}
                  onPress={() => openShop(provider)}
                />
              ))}
              {pair.length === 1 && <View style={styles.filler} />}
            </View>
          ))}
          <Text style={styles.footer}>Showing {providers.length} suppliers</Text>
        </ScrollView>
      )}
    </View>
  );
}

/**
 * Chunk the flat provider list into pairs so the grid can render
 * one row of two tiles at a time. Keeping the chunking here (rather
 * than FlatList with numColumns={2}) lets us keep the pull-to-refresh
 * and footer in a single ScrollView.
 */
function chunkIntoPairs<T>(items: T[]): T[][] {
  const out: T[][] = [];
  for (let i = 0; i < items.length; i += 2) {
    out.push(items.slice(i, i + 2));
  }
  return out;
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

  gridContent: {
    padding: 12,
    gap: 10,
  },
  gridRow: {
    flexDirection: 'row',
    gap: 10,
  },
  // Right-column filler keeps an odd final row's tile at half-width
  // instead of stretching to fill the full row.
  filler: {
    flex: 1,
  },
  footer: {
    marginTop: 12,
    textAlign: 'center',
    fontFamily: fonts.monoSemi,
    fontSize: 11,
    letterSpacing: 1.3,
    textTransform: 'uppercase',
    color: colors.muted,
  },
});
