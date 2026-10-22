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

import { Discipline, activeDisciplines, listDisciplines } from '@/api/disciplines';
import { EventCard, listEvents } from '@/api/events';
import { PROVINCES } from '@/api/provinces';
import { colors, fonts } from '@/theme';
import { EmptyState, ErrorRow, FilterChip, HeroBand, MatchRow, Spinner } from '@/ui';

/**
 * M3 Calendar — information-dense, filter-first.
 *
 * `GET /api/v1/events` with:
 *   - `family` chip (All / Rifle / Handgun / Shotgun / Airgun / Multi)
 *   - `discipline` chip (slug), filtered to the selected family
 *   - `novice` toggle (pinned right of family row)
 *   - `province` chip (slug) — any of the 9 SA provinces
 *   - `from`/`to` date quick-picks: this weekend / 30d / 90d
 *
 * Phase 2 adds the discipline + province + date rows on top of the
 * Phase 1 family-only strip. Deep links from Find continue to pre-set
 * `province` via the route param so the two paths stay compatible.
 *
 * States per BUILD_SPEC §3.2:
 *   M3a Loading           — filter stack stays usable, spinner below
 *   M3b Default           — chip stack + MatchRows grouped by month
 *   M3c Province filter   — the Province row shows the selected pill
 *   M3d Empty after filter — "No matches match your filters." + Clear
 *   M3e Error             — "Couldn't load the calendar. Pull to refresh."
 */

type FamilyValue = 'all' | 'rifle' | 'handgun' | 'shotgun' | 'airgun' | 'multi';

type DateRange = 'any' | 'this-weekend' | 'next-30' | 'next-90';

type Filters = {
  family: FamilyValue;
  discipline: string | null;
  novice: boolean;
  dateRange: DateRange;
};

const FAMILIES: Array<{ value: FamilyValue; label: string }> = [
  { value: 'all', label: 'All' },
  { value: 'rifle', label: 'Rifle' },
  { value: 'handgun', label: 'Handgun' },
  { value: 'shotgun', label: 'Shotgun' },
  { value: 'airgun', label: 'Airgun' },
  { value: 'multi', label: 'Multi' },
];

const DATE_RANGES: Array<{ value: DateRange; label: string }> = [
  { value: 'any', label: 'Any date' },
  { value: 'this-weekend', label: 'This weekend' },
  { value: 'next-30', label: 'Next 30 days' },
  { value: 'next-90', label: 'Next 90 days' },
];

const INITIAL_FILTERS: Filters = {
  family: 'all',
  discipline: null,
  novice: false,
  dateRange: 'any',
};

/**
 * ISO date string (YYYY-MM-DD) for API `from` / `to` params.
 */
function toYmd(date: Date): string {
  const y = date.getFullYear();
  const m = String(date.getMonth() + 1).padStart(2, '0');
  const d = String(date.getDate()).padStart(2, '0');
  return `${y}-${m}-${d}`;
}

/**
 * Resolve a date-range chip to API from/to params.
 *
 * "This weekend" is: today if today is Sunday; today+tomorrow if
 * today is Saturday; otherwise next Saturday + Sunday. Keeps the
 * mental model simple — if the user taps "This weekend" on Thursday
 * they get the coming Sat/Sun; if they tap it on Sunday afternoon
 * they get Sunday only.
 */
function resolveDateRange(range: DateRange): { from: string | null; to: string | null } {
  if (range === 'any') {
    return { from: null, to: null };
  }

  const now = new Date();

  if (range === 'next-30' || range === 'next-90') {
    const end = new Date(now);
    end.setDate(now.getDate() + (range === 'next-30' ? 30 : 90));
    return { from: toYmd(now), to: toYmd(end) };
  }

  // this-weekend
  const dow = now.getDay(); // 0 = Sun, 6 = Sat
  if (dow === 0) {
    return { from: toYmd(now), to: toYmd(now) };
  }
  if (dow === 6) {
    const tomorrow = new Date(now);
    tomorrow.setDate(now.getDate() + 1);
    return { from: toYmd(now), to: toYmd(tomorrow) };
  }

  // Weekday — jump to this coming Sat + Sun.
  const saturday = new Date(now);
  saturday.setDate(now.getDate() + (6 - dow));
  const sunday = new Date(saturday);
  sunday.setDate(saturday.getDate() + 1);
  return { from: toYmd(saturday), to: toYmd(sunday) };
}

/**
 * Group events into month buckets ordered by their first event's date.
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

export default function CalendarScreen() {
  const router = useRouter();
  const params = useLocalSearchParams<{ province?: string }>();
  const [events, setEvents] = useState<EventCard[]>([]);
  const [disciplines, setDisciplines] = useState<Discipline[]>([]);
  const [loading, setLoading] = useState(true);
  const [refreshing, setRefreshing] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [filters, setFilters] = useState<Filters>(INITIAL_FILTERS);

  // Province is driven by the route param so Find deep links continue
  // to pre-select it. The row below lets the user change it too.
  const activeProvince = typeof params.province === 'string' ? params.province : null;

  const load = useCallback(async () => {
    setError(null);
    const { from, to } = resolveDateRange(filters.dateRange);

    try {
      const { events: rows } = await listEvents({
        family: filters.family === 'all' ? null : filters.family,
        discipline: filters.discipline,
        novice: filters.novice,
        province: activeProvince,
        from,
        to,
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

  // Disciplines list is a one-time fetch; chips come from this list
  // regardless of the current family (filtered client-side so the row
  // reacts instantly when the user taps a family chip).
  useEffect(() => {
    void listDisciplines()
      .then((rows) => setDisciplines(activeDisciplines(rows)))
      .catch(() => {
        // Non-fatal: discipline row stays empty, user can still use
        // the other filters. We don't surface this error in the UI
        // because the rest of Calendar still works.
      });
  }, []);

  const sections = useMemo(() => groupByMonth(events), [events]);

  // Discipline chips filtered to the current family. When family is
  // 'all' we show every discipline that has upcoming matches.
  const visibleDisciplines = useMemo(() => {
    if (filters.family === 'all') {
      return disciplines;
    }
    return disciplines.filter((d) => d.family === filters.family);
  }, [disciplines, filters.family]);

  const openMatch = useCallback(
    (slug: string) =>
      router.push({ pathname: '/matches/[slug]', params: { slug, from: 'calendar' } }),
    [router],
  );

  const setProvince = useCallback(
    (slug: string | null) => {
      if (slug === null) {
        router.replace('/calendar');
      } else {
        router.replace({ pathname: '/calendar', params: { province: slug } });
      }
    },
    [router],
  );

  const clearFilters = useCallback(() => {
    setFilters(INITIAL_FILTERS);
    router.replace('/calendar');
  }, [router]);

  const hasAnyFilter =
    filters.family !== 'all' ||
    filters.discipline !== null ||
    filters.novice ||
    filters.dateRange !== 'any' ||
    activeProvince !== null;

  return (
    <View style={styles.container}>
      <HeroBand eyebrow="Full calendar" title="Browse matches" />

      <View style={styles.filters}>
        {/* Row 1: family + Novice toggle */}
        <View style={styles.filterRow}>
          <ScrollView
            horizontal
            showsHorizontalScrollIndicator={false}
            contentContainerStyle={styles.chipScroll}
          >
            {FAMILIES.map((option) => (
              <FilterChip
                key={option.value}
                label={option.label}
                selected={filters.family === option.value}
                onPress={() =>
                  setFilters((prev) => ({
                    ...prev,
                    family: option.value,
                    // Clear the discipline when family changes so the
                    // user doesn't end up with an invalid combo like
                    // family=handgun + discipline=prs.
                    discipline:
                      option.value !== prev.family ? null : prev.discipline,
                  }))
                }
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

        {/* Row 2: discipline (dependent on family). Hidden if no
            visible disciplines — avoids an empty row when the API is
            still loading or the user picked a family with no data. */}
        {visibleDisciplines.length > 0 && (
          <View style={styles.filterRow}>
            <ScrollView
              horizontal
              showsHorizontalScrollIndicator={false}
              contentContainerStyle={styles.chipScroll}
            >
              <FilterChip
                label="Any discipline"
                selected={filters.discipline === null}
                onPress={() => setFilters((prev) => ({ ...prev, discipline: null }))}
              />
              {visibleDisciplines.map((d) => (
                <FilterChip
                  key={d.slug}
                  label={`${d.name} · ${d.upcoming_count}`}
                  selected={filters.discipline === d.slug}
                  onPress={() =>
                    setFilters((prev) => ({ ...prev, discipline: d.slug }))
                  }
                />
              ))}
            </ScrollView>
          </View>
        )}

        {/* Row 3: province chips. Short forms (EC, GP, KZN...) to keep
            the row from blowing out horizontally. */}
        <View style={styles.filterRow}>
          <ScrollView
            horizontal
            showsHorizontalScrollIndicator={false}
            contentContainerStyle={styles.chipScroll}
          >
            <FilterChip
              label="Any province"
              selected={activeProvince === null}
              onPress={() => setProvince(null)}
            />
            {PROVINCES.map((p) => (
              <FilterChip
                key={p.slug}
                label={p.short}
                selected={activeProvince === p.slug}
                onPress={() =>
                  setProvince(activeProvince === p.slug ? null : p.slug)
                }
                accessibilityLabel={p.name}
              />
            ))}
          </ScrollView>
        </View>

        {/* Row 4: date quick-picks. */}
        <View style={styles.filterRow}>
          <ScrollView
            horizontal
            showsHorizontalScrollIndicator={false}
            contentContainerStyle={styles.chipScroll}
          >
            {DATE_RANGES.map((option) => (
              <FilterChip
                key={option.value}
                label={option.label}
                selected={filters.dateRange === option.value}
                onPress={() =>
                  setFilters((prev) => ({ ...prev, dateRange: option.value }))
                }
              />
            ))}
          </ScrollView>
        </View>
      </View>

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
              ? 'No matches match your filters.'
              : 'No upcoming matches yet. Pull to refresh.'}
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

  /* Four-row filter stack under the HeroBand. Each row scrolls
   * horizontally so dense content (10+ disciplines, 9 provinces)
   * never forces a line wrap. */
  filters: {
    backgroundColor: colors.card,
    borderBottomWidth: 1,
    borderBottomColor: colors.rule,
    paddingVertical: 6,
  },
  filterRow: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 10,
    paddingHorizontal: 12,
    paddingVertical: 4,
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

  /* States below the filter stack. */
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
