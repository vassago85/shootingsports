import { Pressable, StyleSheet, Text, View } from 'react-native';

import { colors, fonts, radius } from '@/theme';

type Props = {
  name: string;
  familyLabel: string | null;
  upcomingCount: number;
  onPress: () => void;
};

/**
 * Sport tile for M4l — grouped under Rifle, Handgun, Shotgun, Airgun,
 * Multi headers in a 2-column grid. Each tile shows the discipline
 * name on top and a mono "N UPCOMING" count below.
 *
 * Tapping deep-links to Calendar scoped to that discipline. The spec
 * also lists an R3 sport page (Articles + Magazine covers) as a
 * future state — when that lands, swap the onPress handler to push
 * `/find/sport/[slug]` instead.
 */
export default function SportTile({ name, familyLabel, upcomingCount, onPress }: Props) {
  return (
    <Pressable
      onPress={onPress}
      accessibilityRole="button"
      accessibilityLabel={`${name}, ${upcomingCount} upcoming`}
      style={({ pressed }) => [styles.tile, pressed && styles.pressed]}
    >
      <Text style={styles.name} numberOfLines={2}>
        {name}
      </Text>
      <View style={styles.footerRow}>
        <Text style={styles.count}>
          {upcomingCount} UPCOMING
        </Text>
        {familyLabel !== null && (
          <View style={styles.familyPill}>
            <Text style={styles.familyPillText}>{familyLabel}</Text>
          </View>
        )}
      </View>
    </Pressable>
  );
}

const styles = StyleSheet.create({
  tile: {
    flex: 1,
    minHeight: 118,
    padding: 14,
    borderRadius: radius.card,
    backgroundColor: colors.forest,
    justifyContent: 'space-between',
  },
  pressed: {
    opacity: 0.85,
  },
  name: {
    fontFamily: fonts.display,
    fontSize: 20,
    color: colors.onForest,
    textTransform: 'uppercase',
    letterSpacing: 0.6,
    lineHeight: 24,
  },
  footerRow: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
    gap: 8,
  },
  count: {
    fontFamily: fonts.monoSemi,
    fontSize: 11,
    letterSpacing: 1.3,
    color: colors.lime,
  },
  familyPill: {
    paddingHorizontal: 8,
    paddingVertical: 2,
    borderRadius: 999,
    backgroundColor: 'rgba(255, 255, 255, 0.12)',
  },
  familyPillText: {
    fontFamily: fonts.monoSemi,
    fontSize: 10,
    letterSpacing: 1,
    textTransform: 'uppercase',
    color: colors.onForestMuted,
  },
});
