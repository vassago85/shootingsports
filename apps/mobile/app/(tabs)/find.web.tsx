import { useState } from 'react';
import { ScrollView, StyleSheet, View } from 'react-native';

import ClubsList from '@/find/ClubsList';
import RangesList from '@/find/RangesList';
import ShopsList from '@/find/ShopsList';
import { colors } from '@/theme';
import { FilterChip, HeroBand } from '@/ui';

/**
 * Web override for the Find tab. `react-native-maps` is iOS/Android only
 * (`codegenNativeComponent` is unimplemented on `react-native-web`), so
 * the native screen in `find.tsx` can't run here. Expo Router picks
 * `find.web.tsx` on web and `find.tsx` on native, so this file only
 * matters when someone browses the Expo web preview.
 *
 * The map centroids aren't useful on a desktop viewport anyway, so web
 * exposes the three directory modes directly — Clubs, Ranges, Shops —
 * using the same shared list components the native screen renders. The
 * chip strip, HeroBand and colour tokens all come from the same
 * primitives so native and web stay visually consistent.
 *
 * The Sports mode was removed in 2026-10 — browsing by discipline now
 * lives on the Calendar filter sheet, where the same picker also
 * narrows by date / province.
 */

type FindMode = 'clubs' | 'ranges' | 'shops';

type ModeConfig = {
  value: FindMode;
  label: string;
};

const MODES: ReadonlyArray<ModeConfig> = [
  { value: 'clubs', label: 'Clubs' },
  { value: 'ranges', label: 'Ranges' },
  { value: 'shops', label: 'Shops' },
];

export default function FindScreenWeb() {
  const [mode, setMode] = useState<FindMode>('clubs');

  return (
    <View style={styles.container}>
      <HeroBand eyebrow="Discover" title="Find" />

      <View style={styles.chipStrip}>
        <ScrollView
          horizontal
          showsHorizontalScrollIndicator={false}
          contentContainerStyle={styles.chipRow}
        >
          {MODES.map((option) => (
            <FilterChip
              key={option.value}
              label={option.label}
              selected={mode === option.value}
              onPress={() => setMode(option.value)}
            />
          ))}
        </ScrollView>
      </View>

      <View style={styles.body}>{renderMode(mode)}</View>
    </View>
  );
}

/**
 * Exhaustive switch over FindMode — adding a fourth value is a compile
 * error until a branch lands here. Mirrors the pattern used in the
 * native Find screen.
 */
function renderMode(mode: FindMode) {
  switch (mode) {
    case 'clubs':
      return <ClubsList />;
    case 'ranges':
      return <RangesList />;
    case 'shops':
      return <ShopsList />;
    default: {
      const _exhaustive: never = mode;
      return _exhaustive;
    }
  }
}

const styles = StyleSheet.create({
  container: { flex: 1, backgroundColor: colors.page },

  /* Mode chip strip under the HeroBand. Mirrors the native find.tsx
   * strip. `chipStrip` hugs its content height; the chip row itself
   * is a horizontally scrolling ScrollView whose contentContainer is
   * row-flex. Without this wrapper the parent column flex would
   * stretch each chip vertically to fill remaining space. */
  chipStrip: {
    backgroundColor: colors.card,
    borderBottomWidth: 1,
    borderBottomColor: colors.rule,
    paddingVertical: 10,
  },
  chipRow: {
    flexDirection: 'row',
    gap: 8,
    paddingHorizontal: 12,
    alignItems: 'center',
  },

  /* Fills the rest of the viewport so the list scrolls, not the page. */
  body: { flex: 1 },
});
