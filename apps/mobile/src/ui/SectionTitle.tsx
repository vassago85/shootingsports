import { ReactNode } from 'react';
import { StyleSheet, Text, View, ViewStyle } from 'react-native';

import { colors, fonts } from '@/theme';

type Props = {
  children: ReactNode;
  style?: ViewStyle;
};

/**
 * Section heading.
 *
 * Spec: Saira 20 uppercase, with a 1px link-green rule underneath.
 * The rule is drawn as a bottom border on an inner wrapping view
 * (sized to the text, not full-width) so it looks like a true
 * underline instead of a divider spanning the screen.
 */
export default function SectionTitle({ children, style }: Props) {
  return (
    <View style={[styles.outer, style]}>
      <View style={styles.inner}>
        <Text style={styles.title}>{children}</Text>
      </View>
    </View>
  );
}

const styles = StyleSheet.create({
  outer: {
    // Only the inner view carries the underline so it hugs the text.
    alignItems: 'flex-start',
  },
  inner: {
    borderBottomWidth: 1,
    borderBottomColor: colors.link,
    paddingBottom: 4,
  },
  title: {
    fontFamily: fonts.display,
    fontSize: 20,
    color: colors.ink,
    textTransform: 'uppercase',
    letterSpacing: 0.8,
  },
});
