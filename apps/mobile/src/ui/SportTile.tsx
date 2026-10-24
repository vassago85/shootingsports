import { Pressable, StyleSheet, Text, View } from 'react-native';

import { colors, fonts, radius } from '@/theme';

type Props = {
  name: string;
  familyLabel: string | null;
  upcomingCount: number;
  onPress: () => void;
  /**
   * Set to false when the tile already sits inside a family-labelled
   * group (Rifle, Handgun, …) on Find Sports — the group header
   * conveys the same information, so the per-tile family pill is
   * just visual noise. Defaults to true for callers that render
   * tiles outside a group.
   */
  showFamilyLabel?: boolean;
};

/**
 * Sport tile for M4l — grouped under Rifle, Handgun, Shotgun, Airgun,
 * Multi headers in a 2-column grid. Each tile shows the discipline
 * name on top and a mono "N UPCOMING" count below (or "NO UPCOMING"
 * when the discipline has nothing scheduled, with a muted colour
 * ramp to send tiles with upcoming matches to the top of the page).
 *
 * Tapping deep-links to Calendar scoped to that discipline. The spec
 * also lists an R3 sport page (Articles + Magazine covers) as a
 * future state — when that lands, swap the onPress handler to push
 * `/find/sport/[slug]` instead.
 */
export default function SportTile({
  name,
  familyLabel,
  upcomingCount,
  onPress,
  showFamilyLabel = true,
}: Props) {
  const isMuted = upcomingCount === 0;
  const countLabel = isMuted ? 'NO UPCOMING' : `${upcomingCount} UPCOMING`;

  return (
    <Pressable
      onPress={onPress}
      accessibilityRole="button"
      accessibilityLabel={
        isMuted
          ? `${name}, no upcoming matches`
          : `${name}, ${upcomingCount} upcoming`
      }
      style={({ pressed }) => [
        styles.tile,
        isMuted && styles.tileMuted,
        pressed && styles.pressed,
      ]}
    >
      <Text
        style={[styles.name, isMuted && styles.nameMuted]}
        numberOfLines={2}
      >
        {name}
      </Text>
      <View style={styles.footerRow}>
        <Text style={[styles.count, isMuted && styles.countMuted]}>
          {countLabel}
        </Text>
        {showFamilyLabel && familyLabel !== null && (
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
  // Dark-but-dimmed card for disciplines with nothing on the
  // calendar yet. Still tappable (the Calendar scope still works)
  // but visually clearly a "come back later" option so active
  // tiles rise to the top of the page.
  tileMuted: {
    backgroundColor: colors.tint,
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
  nameMuted: {
    color: colors.muted,
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
  countMuted: {
    color: colors.link,
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
