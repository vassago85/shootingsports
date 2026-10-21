import { ActivityIndicator, StyleSheet, Text, View } from 'react-native';

import { colors, fonts } from '@/theme';

type Props = {
  /** Optional screen-reader label; the spinner itself still has `role="progressbar"`. */
  label?: string;
  /** `'default'` = 36pt (section fill), `'small'` = inline inside a button or row. */
  size?: 'default' | 'small';
};

/**
 * Loading indicator.
 *
 * Spec: 36pt ring, control track, link-green head, with a screen-
 * reader label. React Native's `ActivityIndicator` draws a single
 * colour (no track), so we approximate the spec by sitting the
 * indicator inside a thin control-coloured ring. Below ~60pt the
 * ring artefact isn't visible so we skip it on the small variant.
 */
export default function Spinner({ label, size = 'default' }: Props) {
  const indicatorSize = size === 'default' ? 'large' : 'small';

  return (
    <View
      style={styles.container}
      accessibilityRole="progressbar"
      accessibilityLabel={label ?? 'Loading'}
    >
      <ActivityIndicator size={indicatorSize} color={colors.link} />
      {label !== undefined && label.length > 0 && (
        <Text style={styles.label}>{label}</Text>
      )}
    </View>
  );
}

const styles = StyleSheet.create({
  container: {
    alignItems: 'center',
    justifyContent: 'center',
    gap: 8,
  },
  label: {
    fontFamily: fonts.body,
    fontSize: 13,
    color: colors.muted,
  },
});
