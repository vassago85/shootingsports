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

import { listOrganisations, Organisation } from '@/api/organisations';
import { PROVINCES } from '@/api/provinces';
import { colors, fonts } from '@/theme';
import { ClubRow, EmptyState, ErrorRow, FilterChip, Spinner } from '@/ui';

/**
 * M4f Find Clubs — list of published clubs (and the three
 * membership-adjacent types: series, associations, provincial
 * bodies). Grouped by province. Rows open the website club page
 * in an in-app browser.
 *
 * A "Visitors welcome" toggle sits in the chip row for the single
 * most-asked newcomer question. We don't duplicate it inline on
 * every row — the row itself shows a lime VISITORS pill next to
 * the name when the club has opted in.
 */
export default function ClubsList() {
  const [organisations, setOrganisations] = useState<Organisation[]>([]);
  const [loading, setLoading] = useState(true);
  const [refreshing, setRefreshing] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [province, setProvince] = useState<string | null>(null);
  const [visitorsOnly, setVisitorsOnly] = useState(false);

  const load = useCallback(async () => {
    setError(null);
    try {
      const rows = await listOrganisations({
        province,
        visitors: visitorsOnly,
      });
      setOrganisations(rows);
    } catch (err) {
      setError(
        err instanceof Error ? err.message : "Couldn't load clubs. Pull to retry.",
      );
    } finally {
      setLoading(false);
      setRefreshing(false);
    }
  }, [province, visitorsOnly]);

  useEffect(() => {
    setLoading(true);
    void load();
  }, [load]);

  const sections = useMemo(() => groupByProvince(organisations), [organisations]);

  const openClub = useCallback((organisation: Organisation) => {
    void WebBrowser.openBrowserAsync(organisation.url);
  }, []);

  return (
    <View style={styles.container}>
      <View style={styles.filterStack}>
        <View style={styles.filterRow}>
          <ScrollView
            horizontal
            showsHorizontalScrollIndicator={false}
            style={styles.scrollerFlex}
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
          <View style={styles.divider} />
          <FilterChip
            label="Visitors"
            selected={visitorsOnly}
            onPress={() => setVisitorsOnly((v) => !v)}
          />
        </View>
      </View>

      {loading && organisations.length === 0 ? (
        <View style={styles.centre}>
          <Spinner label="Loading clubs" />
        </View>
      ) : error !== null && organisations.length === 0 ? (
        <View style={styles.centre}>
          <ErrorRow>Couldn't load clubs. Pull to retry.</ErrorRow>
        </View>
      ) : organisations.length === 0 ? (
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
          <EmptyState icon="people-outline">
            {province === null && !visitorsOnly
              ? 'No clubs listed yet. Pull to refresh.'
              : 'No clubs match those filters. Try widening the search.'}
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
            <ClubRow organisation={item} onPress={() => openClub(item)} />
          )}
        />
      )}
    </View>
  );
}

function groupByProvince(
  organisations: Organisation[],
): Array<{ title: string; data: Organisation[] }> {
  const map = new Map<string, Organisation[]>();

  for (const organisation of organisations) {
    const key = organisation.province ?? 'Elsewhere';
    const bucket = map.get(key) ?? [];
    bucket.push(organisation);
    map.set(key, bucket);
  }

  return Array.from(map.entries())
    .sort(([a], [b]) => a.localeCompare(b))
    .map(([title, data]) => ({ title, data }));
}

const styles = StyleSheet.create({
  container: { flex: 1, backgroundColor: colors.page },

  filterStack: {
    backgroundColor: colors.card,
    borderBottomWidth: 1,
    borderBottomColor: colors.rule,
    paddingVertical: 6,
    zIndex: 10,
    elevation: 4,
  },
  filterRow: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 10,
    paddingHorizontal: 12,
    paddingVertical: 4,
  },
  scrollerFlex: {
    flex: 1,
  },
  chipScroll: {
    flexDirection: 'row',
    gap: 8,
    paddingRight: 4,
    alignItems: 'center',
  },
  divider: {
    width: 1,
    height: 28,
    backgroundColor: colors.rule,
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
