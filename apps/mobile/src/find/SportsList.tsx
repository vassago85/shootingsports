import { useRouter } from 'expo-router';
import { useEffect, useMemo, useState } from 'react';
import { RefreshControl, ScrollView, StyleSheet, Text, View } from 'react-native';

import {
  Discipline,
  groupDisciplinesByFamily,
  listDisciplines,
} from '@/api/disciplines';
import { colors, fonts } from '@/theme';
import { EmptyState, ErrorRow, Spinner, SportTile } from '@/ui';

/**
 * M4l Sports — 2-column tile grid grouped under firearm family
 * headers (Rifle, Handgun, Shotgun, Airgun, Multi). Published
 * disciplines with zero upcoming matches are still shown — they're
 * a browse surface, not a filter chip — but the header for a
 * completely empty family is hidden.
 *
 * Grouping lives in `@/api/disciplines` so the Calendar Filters
 * sheet and this grid stay in lock-step on family order + bucketing.
 *
 * Tapping a tile deep-links to the Calendar scoped to that
 * discipline. The spec also lists an R3 `/find/sport/[slug]` page
 * (Articles + Magazines) as a future state; when that lands, swap
 * the push target.
 */

export default function SportsList() {
  const router = useRouter();
  const [disciplines, setDisciplines] = useState<Discipline[]>([]);
  const [loading, setLoading] = useState(true);
  const [refreshing, setRefreshing] = useState(false);
  const [error, setError] = useState<string | null>(null);

  const load = async () => {
    setError(null);
    try {
      const rows = await listDisciplines();
      setDisciplines(rows);
    } catch (err) {
      setError(
        err instanceof Error ? err.message : "Couldn't load sports. Pull to retry.",
      );
    } finally {
      setLoading(false);
      setRefreshing(false);
    }
  };

  useEffect(() => {
    void load();
  }, []);

  const groups = useMemo(() => {
    // Sort each family group by upcoming count (desc) then name
    // (asc) so disciplines with matches on the calendar rise to the
    // top of their family. The 0-upcoming tiles still render
    // (muted) at the bottom — people browsing Rifle want to see
    // every option, not just the ones with content this month.
    const grouped = groupDisciplinesByFamily(disciplines);
    return grouped.map((group) => ({
      ...group,
      disciplines: [...group.disciplines].sort((a, b) => {
        const diff = (b.upcoming_count ?? 0) - (a.upcoming_count ?? 0);
        return diff !== 0 ? diff : a.name.localeCompare(b.name);
      }),
    }));
  }, [disciplines]);

  if (loading && disciplines.length === 0) {
    return (
      <View style={styles.centre}>
        <Spinner label="Loading sports" />
      </View>
    );
  }

  if (error !== null && disciplines.length === 0) {
    return (
      <View style={styles.centre}>
        <ErrorRow>Couldn't load sports. Pull to retry.</ErrorRow>
      </View>
    );
  }

  if (groups.length === 0) {
    return (
      <View style={styles.centre}>
        <EmptyState icon="trophy-outline">
          No sports listed yet. Pull to refresh.
        </EmptyState>
      </View>
    );
  }

  return (
    <ScrollView
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
    >
      {groups.map((group) => (
        <View key={group.key} style={styles.group}>
          <Text style={styles.groupHeader}>{group.label}</Text>
          <View style={styles.grid}>
            {group.disciplines.map((discipline, idx) => (
              <View
                key={discipline.slug}
                // Flex 1 on each tile + 10px row gap in the grid
                // container keeps the pair of tiles the same width
                // whether the row has 2 or (if odd) 1 tile.
                style={[
                  styles.cell,
                  idx % 2 === 0 ? styles.cellLeft : styles.cellRight,
                ]}
              >
                <SportTile
                  name={discipline.name}
                  familyLabel={discipline.family_label}
                  upcomingCount={discipline.upcoming_count}
                  // The group header above this tile already says
                  // Rifle / Handgun / etc — the per-tile pill is
                  // redundant inside family groups and reads as
                  // visual noise.
                  showFamilyLabel={false}
                  onPress={() =>
                    router.push({
                      pathname: '/calendar',
                      params: { discipline: discipline.slug },
                    })
                  }
                />
              </View>
            ))}
            {group.disciplines.length % 2 === 1 && <View style={styles.cell} />}
          </View>
        </View>
      ))}
    </ScrollView>
  );
}

const styles = StyleSheet.create({
  centre: {
    flex: 1,
    alignItems: 'center',
    justifyContent: 'center',
    paddingHorizontal: 16,
    paddingVertical: 32,
  },
  listContent: {
    padding: 16,
    gap: 22,
  },
  group: {
    gap: 10,
  },
  groupHeader: {
    fontFamily: fonts.monoSemi,
    fontSize: 11,
    letterSpacing: 1.6,
    textTransform: 'uppercase',
    color: colors.link,
  },
  grid: {
    flexDirection: 'row',
    flexWrap: 'wrap',
    gap: 10,
  },
  cell: {
    flexBasis: '48%',
    flexGrow: 1,
  },
  cellLeft: {},
  cellRight: {},
});
