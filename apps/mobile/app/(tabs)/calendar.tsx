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

import {
  activeDisciplines,
  Discipline,
  groupDisciplinesByFamily,
  listDisciplines,
} from '@/api/disciplines';
import {
  EventCard,
  EventFilterSuggestion,
  EventFilterSuggestionKey,
  listEvents,
} from '@/api/events';
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
 *   - `discipline` chip (slug) — any discipline, grouped by family
 *     under the sheet's Discipline header
 *   - `novice` toggle
 *   - `province` chip (slug) — any of the 9 SA provinces
 *   - `from`/`to` date quick-picks: this weekend / 30d / 90d
 *
 * **Single filter surface.** Everything lives inside the bottom sheet
 * — Sport (family), Discipline, Province, Date, Beginner. The sticky
 * row under the HeroBand is just the Filters button plus a preview
 * strip of currently-active chips (each individually dismissible).
 * Earlier builds had family chips sitting *outside* the sheet which
 * felt disconnected ("why do I set some filters here and others in a
 * popup?"). One place keeps the model simple.
 *
 * Deep links from Find continue to pre-set `province` via the route
 * param so the two paths stay compatible.
 *
 * States per BUILD_SPEC §3.2:
 *   M3a Loading           — filter row stays usable, spinner below
 *   M3b Default           — Filters button + MatchRows grouped by month
 *   M3c Province filter   — Province chip appears in the preview strip
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
  const [suggestion, setSuggestion] = useState<EventFilterSuggestion | null>(null);

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
      const { events: rows, suggestion: next } = await listEvents({
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
      // The server returns a suggestion only when the result set
      // is empty and a single-filter removal would recover matches.
      // Null the state in every other case so a stale suggestion
      // from the previous filter combo doesn't flash on screen.
      setSuggestion(next);
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

  // Discipline chips are scoped to the Sport chip above them. When
  // Sport is "All" the sheet shows every family; when Sport is a
  // specific family (Handgun, Rifle, Shotgun…) only that family's
  // disciplines are listed. Keeping both chips in the same visible
  // group with a scoped list makes the relationship obvious —
  // Sport narrows the disciplines you can pick.
  const disciplineGroups = useMemo(() => {
    const scoped = filters.family === 'all'
      ? disciplines
      : disciplines.filter((d) => d.family === filters.family);
    return groupDisciplinesByFamily(scoped);
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

  /**
   * Drop a single filter in response to the server's empty-state
   * suggestion ("Remove Beginner-friendly to see 4 matches"). One
   * tap recovers from a dead-end filter combo without clobbering
   * the user's other choices.
   */
  const applySuggestion = useCallback(
    (key: EventFilterSuggestionKey) => {
      switch (key) {
        case 'novice':
          setFilters((prev) => ({ ...prev, novice: false }));
          return;
        case 'weekend':
        case 'from':
        case 'to':
          setFilters((prev) => ({ ...prev, dateRange: 'any' }));
          return;
        case 'province':
          setProvince(null);
          return;
        case 'discipline':
          setFilters((prev) => ({ ...prev, discipline: null }));
          return;
        case 'family':
          setFilters((prev) => ({ ...prev, family: 'all' }));
          return;
        case 'confirmed':
        case 'kind':
          // No chip in the sheet maps to these; the server shouldn't
          // suggest them for the current client, but ignore safely
          // just in case a future screen adds the filter and this
          // code is still on an older build. Exhaustive to keep
          // future keys from slipping through unhandled.
          return;
        default: {
          const exhaustive: never = key;
          throw new Error(`Unhandled suggestion filter: ${String(exhaustive)}`);
        }
      }
    },
    [setProvince],
  );

  /**
   * Human-readable label matching the chip the suggestion refers to.
   * Keeping the copy on the client (not the server) means it tracks
   * the chip label the user is looking at even when a sheet string
   * changes without a backend deploy.
   */
  const suggestionLabel = useMemo(() => {
    if (suggestion === null) {
      return null;
    }
    switch (suggestion.filter) {
      case 'novice':
        return 'Beginner-friendly';
      case 'weekend':
      case 'from':
      case 'to':
        return 'the date filter';
      case 'province':
        return 'the province filter';
      case 'discipline':
        return 'the discipline filter';
      case 'family':
        return 'the sport filter';
      case 'confirmed':
        return 'the confirmed-only filter';
      case 'kind':
        return 'the match-type filter';
      default: {
        const exhaustive: never = suggestion.filter;
        throw new Error(`Unhandled suggestion filter label: ${String(exhaustive)}`);
      }
    }
  }, [suggestion]);

  const hasAnyFilter =
    filters.family !== 'all' ||
    filters.discipline !== null ||
    filters.novice ||
    filters.dateRange !== 'any' ||
    activeProvince !== null ||
    activeVenue !== null;

  // "Filters" button badge: count every active filter the user can
  // reach through the sheet (sport, discipline, province, date,
  // novice). Venue is a route-driven deep-link scope that always
  // surfaces as its own chip, so it's not counted here.
  const sheetActiveCount =
    (filters.family !== 'all' ? 1 : 0) +
    (filters.discipline !== null ? 1 : 0) +
    (activeProvince !== null ? 1 : 0) +
    (filters.dateRange !== 'any' ? 1 : 0) +
    (filters.novice ? 1 : 0);

  const sheetHasFilter = sheetActiveCount > 0;

  const activeFamilyLabel = useMemo(() => {
    if (filters.family === 'all') {
      return null;
    }
    return FAMILIES.find((f) => f.value === filters.family)?.label ?? null;
  }, [filters.family]);

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
        {/* Single sticky filter row: Filters trigger + scrollable
            preview of currently active chips. Each chip clears its
            own filter without opening the sheet. Venue sits first
            because it's the narrowest, user-initiated scope (they
            tapped a specific pin on the map). */}
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
                accessibilityLabel="Clear range filter"
              />
            )}
            {activeProvinceLabel !== null && (
              <FilterChip
                label={activeProvinceLabel}
                selected
                removable
                onPress={() => setProvince(null)}
                accessibilityLabel="Clear province filter"
              />
            )}
            {activeFamilyLabel !== null && (
              <FilterChip
                label={activeFamilyLabel}
                selected
                removable
                onPress={() =>
                  setFilters((prev) => ({
                    ...prev,
                    family: 'all',
                    // Clearing the sport also clears a discipline
                    // scoped to it — otherwise the user ends up with
                    // "Any sport · PRS" which reads nonsensically.
                    discipline:
                      disciplines.find((d) => d.slug === prev.discipline)
                        ?.family === filters.family
                        ? null
                        : prev.discipline,
                  }))
                }
                accessibilityLabel="Clear sport filter"
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
                accessibilityLabel="Clear discipline filter"
              />
            )}
            {filters.novice && (
              <FilterChip
                label="Beginner"
                selected
                removable
                onPress={() =>
                  setFilters((prev) => ({ ...prev, novice: false }))
                }
                accessibilityLabel="Clear beginner-friendly filter"
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
                accessibilityLabel="Clear date filter"
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
        <View style={styles.sheetGroup}>
          <Text style={styles.sheetGroupLabel}>Sport</Text>
          <View style={styles.sheetChipWrap}>
            {FAMILIES.map((option) => (
              <FilterChip
                key={option.value}
                label={option.label}
                selected={filters.family === option.value}
                onPress={() =>
                  setFilters((prev) => {
                    // Clear the discipline when switching to a family
                    // the current discipline doesn't belong to — the
                    // combination family=handgun+discipline=prs would
                    // return nothing. Keep it when the discipline
                    // belongs to the new family (user picked PRS then
                    // tapped Rifle to narrow further).
                    const activeDiscipline = disciplines.find(
                      (d) => d.slug === prev.discipline,
                    );
                    const shouldClear =
                      prev.discipline !== null &&
                      option.value !== 'all' &&
                      activeDiscipline?.family !== option.value;
                    return {
                      ...prev,
                      family: option.value,
                      discipline: shouldClear ? null : prev.discipline,
                    };
                  })
                }
              />
            ))}
          </View>
        </View>

        <View style={styles.sheetGroup}>
          <Text style={styles.sheetGroupLabel}>Discipline</Text>
          {disciplineGroups.length === 0 ? (
            // Disciplines are still fetching. We used to hide the
            // whole group here, which made the sheet re-flow once the
            // list arrived. Show a placeholder so the layout stays
            // stable instead.
            <Text style={styles.sheetPlaceholder}>Loading disciplines…</Text>
          ) : (
            <View style={styles.sheetChipWrap}>
              <FilterChip
                label="Any discipline"
                selected={filters.discipline === null}
                onPress={() =>
                  setFilters((prev) => ({
                    ...prev,
                    discipline: null,
                    // Don't widen family when clearing — the user
                    // might still want their family chip to scope
                    // the broader list.
                  }))
                }
              />
            </View>
          )}
          {disciplineGroups.map((group) => (
            <View key={group.key} style={styles.disciplineGroup}>
              {/* Hide the family subheader when there's only one
                  group showing — the Sport chip above already tells
                  the user they're looking at Handgun, so repeating
                  "HANDGUN" here is just more things to read. */}
              {disciplineGroups.length > 1 && (
                <Text style={styles.disciplineFamilyHeader}>{group.label}</Text>
              )}
              <View style={styles.sheetChipWrap}>
                {group.disciplines.map((d) => (
                  <FilterChip
                    key={d.slug}
                    label={d.name}
                    selected={filters.discipline === d.slug}
                    onPress={() =>
                      setFilters((prev) => ({
                        ...prev,
                        discipline: d.slug,
                        // Auto-align the family chip so the inline
                        // row outside the sheet reflects the user's
                        // intent. Picking an "all"-family discipline
                        // (unusual) leaves family untouched.
                        family: (d.family ?? prev.family) as FamilyValue,
                      }))
                    }
                  />
                ))}
              </View>
            </View>
          ))}
        </View>

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

        <View style={styles.sheetGroup}>
          <Text style={styles.sheetGroupLabel}>Beginner</Text>
          <View style={styles.sheetChipWrap}>
            <FilterChip
              label="Beginner-friendly only"
              selected={filters.novice}
              onPress={() =>
                setFilters((prev) => ({ ...prev, novice: !prev.novice }))
              }
            />
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
            action={
              // Prefer the server's one-tap suggestion over the
              // blunt "Clear filters" action — it keeps the rest of
              // the user's filter combo intact so the empty state
              // isn't a punishment for picking too much.
              suggestion !== null && suggestionLabel !== null
                ? {
                    label: `Remove ${suggestionLabel} to see ${suggestion.would_show} ${suggestion.would_show === 1 ? 'match' : 'matches'}`,
                    onPress: () => applySuggestion(suggestion.filter),
                  }
                : hasAnyFilter
                  ? { label: 'Clear filters', onPress: clearFilters }
                  : undefined
            }
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
  // Lets the preview chip ScrollView consume the remaining space to
  // the right of the Filters trigger button.
  scrollerFlex: {
    flex: 1,
  },
  chipScroll: {
    flexDirection: 'row',
    gap: 8,
    paddingRight: 4,
    alignItems: 'center',
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
  sheetPlaceholder: {
    fontFamily: fonts.body,
    fontSize: 13,
    color: colors.muted,
    paddingVertical: 4,
  },
  /* Discipline sub-group under the Discipline sheet-group header. */
  disciplineGroup: {
    gap: 6,
    marginTop: 4,
  },
  disciplineFamilyHeader: {
    fontFamily: fonts.mono,
    fontSize: 10,
    letterSpacing: 1.2,
    textTransform: 'uppercase',
    color: colors.muted,
  },
});
