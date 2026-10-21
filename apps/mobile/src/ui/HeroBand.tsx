import { ReactNode } from 'react';
import { StyleSheet, Text, View, ViewStyle } from 'react-native';
import { useSafeAreaInsets } from 'react-native-safe-area-context';

import { colors, fonts } from '@/theme';

type Props = {
  /** Mono eyebrow label above the H1 — "National calendar", "My home", etc. */
  eyebrow: string;
  /** H1 text — Saira 36 uppercase white on forest. */
  title: string;
  /** Optional pill at the top-right (e.g. PlanPill). */
  pill?: ReactNode;
  /** Any extra content below the title inside the hero (filter strip etc). */
  children?: ReactNode;
  style?: ViewStyle;
};

/**
 * Hero band.
 *
 * Forest background that extends under the status bar and Dynamic
 * Island (spec §3.1: "Do not draw a fake status bar"). Content
 * starts below the top safe-area inset. The height is driven by the
 * eyebrow + title + pill + optional children; we never hard-code
 * the 59pt top-inset value.
 *
 * Layout:
 *
 *   [eyebrow]                                  [pill]
 *   [H1 title                                       ]
 *   [children ....................................]
 */
export default function HeroBand({ eyebrow, title, pill, children, style }: Props) {
  const insets = useSafeAreaInsets();

  return (
    <View style={[styles.band, { paddingTop: insets.top + 14 }, style]}>
      <View style={styles.topRow}>
        <Text style={styles.eyebrow}>{eyebrow}</Text>
        {pill}
      </View>
      <Text style={styles.title}>{title}</Text>
      {children}
    </View>
  );
}

const styles = StyleSheet.create({
  band: {
    backgroundColor: colors.forest,
    paddingHorizontal: 20,
    paddingBottom: 18,
    gap: 10,
  },
  topRow: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
    gap: 10,
  },
  eyebrow: {
    fontFamily: fonts.monoSemi,
    fontSize: 12,
    letterSpacing: 1.6,
    textTransform: 'uppercase',
    color: colors.lime,
  },
  title: {
    fontFamily: fonts.display,
    fontSize: 36,
    lineHeight: 36,
    color: colors.onForest,
    textTransform: 'uppercase',
    letterSpacing: 0.5,
  },
});
