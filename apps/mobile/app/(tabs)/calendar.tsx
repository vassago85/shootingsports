import { useRouter, useLocalSearchParams } from 'expo-router';
import { useCallback, useEffect, useMemo, useState } from 'react';
import {
  Pressable,
  RefreshControl,
  ScrollView,
  SectionList,
  StyleSheet,
  Text,
  View,
} from 'react-native';

import { Ionicons } from '@expo/vector-icons';

import { Discipline, activeDisciplines, listDisciplines } from '@/api/disciplines';
import { EventCard, listEvents } from '@/api/events';
import { PROVINCES } from '@/api/provinces';
import { colors, fonts } from '@/theme';
import {
  BottomSheet,
  EmptyState,
  ErrorRow,
  FilterChip,
  HeroBand,
  MatchRow,
  Spinner,
} from '@/ui';

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
 * Resolve the "next N days" quick-picks. "This weekend" is a separate
 * server-side flag (`weekend=1`) that uses `App\Support\ThisWeekend`,
 * which includes Friday through Sunday — the window shooters actually
 * mean when they say "this weekend".
 */
function resolveDateRange(range: DateRange): { from: string | null; to: string | null } {
  if (range === 'any' || range === 'this-weekend') {
    return { from: null, to: null };
  }

  const now = new Date();
  const end = new Date(now);
  end.setDate(now.getDate() + (range === 'next-30' ? 30 : 90));
  return { from: toYmd(now), to: toYmd(end) };
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
  const params = useLocalSearchParams<{
    province?: string;
    venue?: string;
    venueName?: string;
    discipline?: string;
  }>();
  const [events, setEvents] = useState<EventCard[]>([]);
  const [disciplines, setDisciplines] = useState<Discipline[]>([]);
  const [loading, setLoading] = useState(true);
  const [refreshing, setRefreshing] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [filters, setFilters] = useState<Filters>(INITIAL_FILTERS);
  const [sheetOpen, setSheetOpen] = useState(false);

  // Province + venue are driven by route params so Find deep links
  // continue to pre-select them. Venue is the user tapping a specific
  // range pin on the map; province is the broader scope (either
  // explicitly set via the sheet or carried alongside a venue tap so
  // the user has a clean "widen the search" fallback).
  const activeProvince = typeof params.province === 'string' ? params.province : null;
  const activeVenue = typeof params.venue === 'string' ? params.venue : null;
  const activeVenueName =
    typeof params.venueName === 'string' ? params.venueName : null;
  const deepLinkDiscipline =
    typeof params.discipline === 'string' ? params.discipline : null;

  // When Find hands off with `?discipline=`, seed the discipline
  // filter once so the user lands on the right chip. We only run
  // this when the slug actually changes to avoid clobbering the
  // user's choice if they later picked another discipline manually.
  useEffect(() => {
    if (deepLinkDiscipline !== null) {
      setFilters((prev) =>
        prev.discipline === deepLinkDiscipline
          ? prev
          : { ...prev, discipline: deepLinkDiscipline },
      );
    }
  }, [deepLinkDiscipline]);

  const load = useCallback(async () => {
    setError(null);
    const { from, to } = resolveDateRange(filters.dateRange);

    try {
      const { events: rows } = await listEvents({
        family: filters.family === 'all' ? null : filters.family,
        discipline: filters.discipline,
        novice: filters.novice,
        province: activeProvince,
        venue: activeVenue,
        // Fri-Sun window; the backend's `?weekend=1` flag maps to
        // `App\Support\ThisWeekend::range()` which spans Friday
        // through Sunday — the window shooters actually mean.
        weekend: filters.dateRange === 'this-weekend',
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
  }, [filters, activeProvince, activeVenue]);

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
        // Clearing the province also clears the venue scope: the
        // venue filter only makes sense inside a province context.
        router.replace('/calendar');
      } else {
        router.replace({ pathname: '/calendar', params: { province: slug } });
      }
    },
    [router],
  );

  const clearVenue = useCallback(() => {
    // Keep the province context when the user drops the venue chip —
    // "show me everything in this province" is a reasonable next step
    // after tapping "× Thabazimbi".
    if (activeProvince !== null) {
      router.replace({ pathname: '/calendar', params: { province: activeProvince } });
    } else {
      router.replace('/calendar');
    }
  }, [router, activeProvince]);

  const clearFilters = useCallback(() => {
    setFilters(INITIAL_FILTERS);
    router.replace('/calendar');
  }, [router]);

  const hasAnyFilter =
    filters.family !== 'all' ||
    filters.discipline !== null ||
    filters.novice ||
    filters.dateRange !== 'any' ||
    activeProvince !== null ||
    activeVenue !== null;

  // "Filters" button badge: count every active sheet filter so the
  // user can see at a glance how many are hidden behind the button.
  // Family and Novice live inline and are not counted. Venue is
  // always surfaced as its own chip (route-driven), so it's not
  // counted here either.
  const sheetActiveCount =
    (filters.discipline !== null ? 1 : 0) +
    (activeProvince !== null ? 1 : 0) +
    (filters.dateRange !== 'any' ? 1 : 0);

  const sheetHasFilter = sheetActiveCount > 0;

  const dateRangeLabel = useMemo(() => {
    const match = DATE_RANGES.find((r) => r.value === filters.dateRange);
    return match ? match.label : 'Any date';
  }, [filters.dateRange]);

  const activeProvinceLabel = useMemo(() => {
    if (activeProvince === null) {
      return null;
    }
    return PROVINCES.find((p) => p.slug === activeProvince)?.short ?? null;
  }, [activeProvince]);

  const activeDisciplineLabel = useMemo(() => {
    if (filters.discipline === null) {
      return null;
    }
    return disciplines.find((d) => d.slug === filters.discipline)?.name ?? null;
  }, [disciplines, filters.discipline]);

  return (
    <View style={styles.container}>
      <HeroBand
        eyebrow={activeVenue !== null ? 'At this range' : 'Full calendar'}
        title={activeVenue !== null ? (activeVenueName ?? 'This range') : 'Browse matches'}
      />

      <View style={styles.filters}>
        {/* Row 1: family + Novice toggle. Novice sits in its own
            fixed-width column on the right; the ScrollView takes the
            remaining space with `flex: 1` so the trailing family chip
            ("Shotgun"/"Airgun") no longer clips against the divider. */}
        <View style={styles.filterRow}>
          <ScrollView
            horizontal
            showsHorizontalScrollIndicator={false}
            style={styles.scrollerFlex}
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

        {/* Row 2: trigger row — compact "Filters" button + a scroll
            of chips showing what's currently active in the sheet.
            Keeps the main screen to two filter rows while the user
            can still see at a glance which filters are set. */}
        <View style={styles.filterRow}>
          <Pressable
            onPress={() => setSheetOpen(true)}
            accessibilityRole="button"
            accessibilityLabel={
              sheetHasFilter
                ? `Filters, ${sheetActiveCount} active`
                : 'Open filters'
            }
            style={({ pressed }) => [
              styles.sheetTrigger,
              sheetHasFilter && styles.sheetTriggerActive,
              pressed && styles.sheetTriggerPressed,
            ]}
          >
            <Ionicons
              name="options-outline"
              size={16}
              color={sheetHasFilter ? colors.page : colors.ink}
            />
            <Text
              style={[
                styles.sheetTriggerLabel,
                sheetHasFilter && styles.sheetTriggerLabelActive,
              ]}
            >
              Filters
              {sheetHasFilter ? ` · ${sheetActiveCount}` : ''}
            </Text>
          </Pressable>

          {/* Active-filter preview — tap any chip's × to clear it
              individually without opening the sheet. Venue sits
              first because it's the narrowest, user-initiated scope
              (they tapped a specific pin on the map). */}
          <ScrollView
            horizontal
            showsHorizontalScrollIndicator={false}
            style={styles.scrollerFlex}
            contentContainerStyle={styles.chipScroll}
          >
            {activeVenue !== null && (
              <FilterChip
                label={activeVenueName ?? activeVenue}
                selected
                removable
                onPress={clearVenue}
                accessibilityLabel={`Clear range filter`}
              />
            )}
            {activeProvinceLabel !== null && (
              <FilterChip
                label={activeProvinceLabel}
                selected
                removable
                onPress={() => setProvince(null)}
                accessibilityLabel={`Clear province filter`}
              />
            )}
            {activeDisciplineLabel !== null && (
              <FilterChip
                label={activeDisciplineLabel}
                selected
                removable
                onPress={() =>
                  setFilters((prev) => ({ ...prev, discipline: null }))
                }
                accessibilityLabel={`Clear discipline filter`}
              />
            )}
            {filters.dateRange !== 'any' && (
              <FilterChip
                label={dateRangeLabel}
                selected
                removable
                onPress={() =>
                  setFilters((prev) => ({ ...prev, dateRange: 'any' }))
                }
                accessibilityLabel={`Clear date filter`}
              />
            )}
          </ScrollView>
        </View>
      </View>

      <BottomSheet
        visible={sheetOpen}
        title="Filters"
        onClose={() => setSheetOpen(false)}
        primaryAction={{
          label: 'Done',
          onPress: () => setSheetOpen(false),
        }}
        secondaryAction={{
          label: 'Clear all',
          onPress: clearFilters,
          disabled: !hasAnyFilter,
        }}
      >
        {visibleDisciplines.length > 0 && (
          <View style={styles.sheetGroup}>
            <Text style={styles.sheetGroupLabel}>Discipline</Text>
            <View style={styles.sheetChipWrap}>
              <FilterChip
                label="Any discipline"
                selected={filters.discipline === null}
                onPress={() =>
                  setFilters((prev) => ({ ...prev, discipline: null }))
                }
              />
              {visibleDisciplines.map((d) => (
                <FilterChip
                  key={d.slug}
                  label={d.name}
                  selected={filters.discipline === d.slug}
                  onPress={() =>
                    setFilters((prev) => ({ ...prev, discipline: d.slug }))
                  }
                />
              ))}
            </View>
          </View>
        )}

        <View style={styles.sheetGroup}>
          <Text style={styles.sheetGroupLabel}>Province</Text>
          <View style={styles.sheetChipWrap}>
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
          </View>
        </View>

        <View style={styles.sheetGroup}>
          <Text style={styles.sheetGroupLabel}>Date</Text>
          <View style={styles.sheetChipWrap}>
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
          </View>
        </View>
      </BottomSheet>

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
    // Keep the filter block above the SectionList so sticky section
    // headers pin cleanly BELOW it instead of sliding under the chip
    // rows. Both props needed: `zIndex` for iOS, `elevation` for
    // Android's native shadow stack.
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
  // Row 1 only — lets the family ScrollView consume the remaining
  // space before the Novice chip sits in its fixed column.
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
    // Tightened to visually "stick" cleanly below the filter stack
    // once scrolling pins the header. The old 16/8 padding read as
    // "half hidden" because the big top gap made the pinned header
    // float too far from the filters.
    paddingTop: 10,
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

  /* Row 2 "Filters" trigger + active-chip preview. */
  sheetTrigger: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 6,
    minHeight: 36,
    paddingHorizontal: 12,
    borderRadius: 999,
    borderWidth: 1,
    borderColor: colors.chipBorder,
    backgroundColor: colors.card,
  },
  sheetTriggerActive: {
    borderColor: colors.forest,
    backgroundColor: colors.forest,
  },
  sheetTriggerPressed: {
    opacity: 0.7,
  },
  sheetTriggerLabel: {
    fontFamily: fonts.bodySemi,
    fontSize: 13,
    color: colors.ink,
    letterSpacing: 0.3,
  },
  sheetTriggerLabelActive: {
    color: colors.page,
  },

  /* Grouped chip stack inside the bottom sheet. */
  sheetGroup: {
    gap: 10,
  },
  sheetGroupLabel: {
    fontFamily: fonts.monoSemi,
    fontSize: 11,
    letterSpacing: 1.6,
    textTransform: 'uppercase',
    color: colors.link,
  },
  sheetChipWrap: {
    flexDirection: 'row',
    flexWrap: 'wrap',
    gap: 8,
  },
});
