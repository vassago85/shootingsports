import { useRouter, useLocalSearchParams } from 'expo-router';
import { useCallback, useEffect, useMemo, useState } from 'react';
import {
  RefreshControl,
  ScrollView,
  SectionList,
  StyleSheet,
  Text,
  View,
} from 'react-native';

import { EventCard, listEvents } from '@/api/events';
import { colors, fonts } from '@/theme';
import { EmptyState, ErrorRow, FilterChip, GhostButton, HeroBand, MatchRow, Spinner } from '@/ui';

/**
 * M3 Calendar — information-dense, filter-first.
 *
 * `GET /api/v1/events` with:
 *   - `family` chip (All / Rifle / Handgun / Shotgun)
 *   - `novice` boolean toggle (always visible, pinned right)
 *   - optional `province` slug when the user arrived from Find (M4b)
 *
 * States per BUILD_SPEC §3.2:
 *   M3a Loading           — filter strip stays usable, spinner below
 *   M3b Default           — chip strip + MatchRows grouped by month
 *   M3c Province filter   — second row with removable "Province: X ×" chip
 *   M3d Empty after filter — "No matches match your filters." + Clear
 *   M3e Error             — "Couldn't load the calendar. Pull to refresh."
 *
 * Date / distance / text-search filters are **not** built — spec notes
 * only to add them when the API supports them. The API side already
 * accepts `discipline`, `from`, `to`, `confirmed`, `radius`, `near`
 * (spec §6 Phase 5), but the UI is explicitly flagged as future work.
 */

type FamilyValue = 'all' | 'rifle' | 'handgun' | 'shotgun';

type Filters = {
  family: FamilyValue;
  novice: boolean;
};

const FAMILIES: Array<{ value: FamilyValue; label: string }> = [
  { value: 'all', label: 'All' },
  { value: 'rifle', label: 'Rifle' },
  { value: 'handgun', label: 'Handgun' },
  { value: 'shotgun', label: 'Shotgun' },
];

const INITIAL_FILTERS: Filters = { family: 'all', novice: false };

/**
 * Group events into month buckets ordered by their first event's date.
 * Returns the shape SectionList expects.
 */
function groupByMonth(events: EventCard[]): Array<{ title: string; data: EventCard[] }> {
  const groups = new Map<string, EventCard[]>();

  for (const event of events) {
    const key =
      event.starts_at !== null
        ? new Date(event.starts_at)
            .toLocaleDateString('en-ZA', { month: 'long', year: 'numeric' })
            .toUpperCase()
        : 'DATE TBC';

    const bucket = groups.get(key) ?? [];
    bucket.push(event);
    groups.set(key, bucket);
  }

  return Array.from(groups.entries()).map(([title, data]) => ({ title, data }));
}

/**
 * Resolve a province slug to its display name by looking at the
 * loaded events. Falls back to the slug if nothing matches (e.g. the
 * current filter returned zero rows). Returns the slug exactly as
 * passed if it's falsy.
 */
function provinceDisplayName(slug: string, events: EventCard[]): string {
  for (const event of events) {
    const province = event.venue?.data?.province ?? null;
    if (province !== null && province.toLowerCase().replace(/\s+/g, '-') === slug.toLowerCase()) {
      return province;
    }
  }

  // Humanise the slug so it reads better than `western-cape` while
  // we wait for an event that gives us the real name.
  return slug
    .split('-')
    .map((part) => part.charAt(0).toUpperCase() + part.slice(1))
    .join(' ');
}

export default function CalendarScreen() {
  const router = useRouter();
  const params = useLocalSearchParams<{ province?: string }>();
  const [events, setEvents] = useState<EventCard[]>([]);
  const [loading, setLoading] = useState(true);
  const [refreshing, setRefreshing] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [filters, setFilters] = useState<Filters>(INITIAL_FILTERS);

  const activeProvince = typeof params.province === 'string' ? params.province : null;

  const load = useCallback(async () => {
    setError(null);
    try {
      const { events: rows } = await listEvents({
        family: filters.family === 'all' ? null : filters.family,
        novice: filters.novice,
        province: activeProvince,
      });
      setEvents(rows);
    } catch (err) {
      setError(err instanceof Error ? err.message : 'Could not load the calendar.');
    } finally {
      setLoading(false);
      setRefreshing(false);
    }
  }, [filters, activeProvince]);

  useEffect(() => {
    setLoading(true);
    void load();
  }, [load]);

  const sections = useMemo(() => groupByMonth(events), [events]);
  const openMatch = useCallback(
    (slug: string) =>
      router.push({ pathname: '/matches/[slug]', params: { slug, from: 'calendar' } }),
    [router],
  );

  const clearFilters = useCallback(() => {
    setFilters(INITIAL_FILTERS);
    // Replace rather than push so Back doesn't drop the user into the
    // old filter state again.
    router.replace('/calendar');
  }, [router]);

  const clearProvince = useCallback(() => {
    router.replace('/calendar');
  }, [router]);

  const hasAnyFilter = filters.family !== 'all' || filters.novice || activeProvince !== null;

  return (
    <View style={styles.container}>
      <HeroBand eyebrow="Full calendar" title="Browse matches" />

      <View style={styles.filterStrip}>
        <ScrollView
          horizontal
          showsHorizontalScrollIndicator={false}
          contentContainerStyle={styles.familyRow}
        >
          {FAMILIES.map((option) => (
            <FilterChip
              key={option.value}
              label={option.label}
              selected={filters.family === option.value}
              onPress={() => setFilters((prev) => ({ ...prev, family: option.value }))}
            />
          ))}
        </ScrollView>
        <View style={styles.divider} />
        <FilterChip
          label="Novice"
          selected={filters.novice}
          onPress={() => setFilters((prev) => ({ ...prev, novice: !prev.novice }))}
        />
      </View>

      {/* M3c — province filter row from a Find deep link. */}
      {activeProvince !== null && (
        <View style={styles.provinceRow}>
          <Text style={styles.provinceLabel}>Province</Text>
          <FilterChip
            label={`${provinceDisplayName(activeProvince, events)} ×`}
            selected
            removable
            onPress={clearProvince}
            accessibilityLabel={`Clear province filter: ${provinceDisplayName(activeProvince, events)}`}
          />
        </View>
      )}

      {loading ? (
        <View style={styles.loadingWrap}>
          <Spinner label="Loading matches" />
        </View>
      ) : error !== null ? (
        <View style={styles.messageWrap}>
          <ErrorRow>Couldn't load the calendar. Pull to refresh.</ErrorRow>
        </View>
      ) : events.length === 0 ? (
        <ScrollView
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
          <EmptyState
            icon="search-outline"
            action={hasAnyFilter ? { label: 'Clear filters', onPress: clearFilters } : undefined}
          >
            {hasAnyFilter
              ? "No matches match your filters."
              : "No upcoming matches yet. Pull to refresh."}
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
            <View style={styles.monthHeaderWrap}>
              <Text style={styles.monthHeader}>{section.title}</Text>
            </View>
          )}
          renderItem={({ item }) => (
            <MatchRow event={item} onPress={() => openMatch(item.slug)} />
          )}
        />
      )}
    </View>
  );
}

const styles = StyleSheet.create({
  container: { flex: 1, backgroundColor: colors.page },

  /* Sticky filter strip under the HeroBand.
   * Layout: horizontally-scrolling family chips | 1px vertical divider | fixed Novice chip. */
  filterStrip: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 10,
    paddingHorizontal: 12,
    paddingVertical: 10,
    backgroundColor: colors.card,
    borderBottomWidth: 1,
    borderBottomColor: colors.rule,
  },
  familyRow: { flexDirection: 'row', gap: 8, paddingRight: 4, alignItems: 'center' },
  divider: {
    width: 1,
    height: 28,
    backgroundColor: colors.rule,
  },

  /* M3c removable-province row. */
  provinceRow: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 10,
    paddingHorizontal: 16,
    paddingVertical: 10,
    backgroundColor: colors.page,
    borderBottomWidth: 1,
    borderBottomColor: colors.rule,
  },
  provinceLabel: {
    fontFamily: fonts.monoSemi,
    fontSize: 11,
    color: colors.muted,
    letterSpacing: 1.4,
    textTransform: 'uppercase',
  },

  /* States below the strip. */
  loadingWrap: {
    flex: 1,
    alignItems: 'center',
    justifyContent: 'center',
    paddingVertical: 48,
  },
  messageWrap: { padding: 16 },

  /* MatchRow list with sticky month headers. */
  listContent: { paddingBottom: 24 },
  monthHeaderWrap: {
    paddingHorizontal: 16,
    paddingTop: 16,
    paddingBottom: 8,
    backgroundColor: colors.page,
  },
  monthHeader: {
    fontFamily: fonts.monoSemi,
    fontSize: 11,
    letterSpacing: 1.6,
    textTransform: 'uppercase',
    color: colors.link,
  },
});
