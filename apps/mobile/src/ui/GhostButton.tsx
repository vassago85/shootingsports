import { Pressable, StyleSheet, Text, ViewStyle } from 'react-native';

import { colors, fonts, radius, size } from '@/theme';

type Props = {
  label: string;
  onPress: () => void;
  disabled?: boolean;
  accessibilityLabel?: string;
  style?: ViewStyle;
};

/**
 * Secondary action. Transparent fill, link-green border and text.
 *
 * Used for secondary paths like "Browse the full calendar", "Open
 * match page", "Clear filters". Same height and radius as
 * {@link PrimaryButton} so pairs line up.
 */
export default function GhostButton({
  label,
  onPress,
  disabled = false,
  accessibilityLabel,
  style,
}: Props) {
  return (
    <Pressable
      onPress={onPress}
      disabled={disabled}
      accessibilityRole="button"
      accessibilityLabel={accessibilityLabel ?? label}
      accessibilityState={{ disabled }}
      style={({ pressed }) => [
        styles.base,
        disabled && styles.disabled,
        pressed && !disabled && styles.pressed,
        style,
      ]}
    >
      <Text style={styles.label}>{label}</Text>
    </Pressable>
  );
}

const styles = StyleSheet.create({
  base: {
    minHeight: size.button,
    paddingHorizontal: 18,
    borderRadius: radius.button,
    borderWidth: 1,
    borderColor: colors.link,
    backgroundColor: 'transparent',
    alignItems: 'center',
    justifyContent: 'center',
  },
  pressed: {
    opacity: 0.6,
  },
  disabled: {
    opacity: 0.4,
  },
  label: {
    fontFamily: fonts.display,
    fontSize: 16,
    color: colors.link,
    textTransform: 'uppercase',
    letterSpacing: 1.3,
  },
});
