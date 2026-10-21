import {
  ActivityIndicator,
  Pressable,
  StyleSheet,
  Text,
  ViewStyle,
} from 'react-native';

import { colors, fonts, radius, size } from '@/theme';

type Props = {
  label: string;
  onPress: () => void;
  /** Shows a spinner inside the button and disables presses. */
  loading?: boolean;
  /** Disables the button and dims the fill. */
  disabled?: boolean;
  /** Optional accessibility label override; falls back to `label`. */
  accessibilityLabel?: string;
  /** For edge cases where the button needs to stretch or inset. */
  style?: ViewStyle;
};

/**
 * Primary call-to-action button.
 *
 * Lime fill, ink text, Saira 16 uppercase, 48pt minimum,
 * radius 10. One per view ideally — if a screen needs two primary
 * actions, the design brief says re-think the hierarchy.
 */
export default function PrimaryButton({
  label,
  onPress,
  loading = false,
  disabled = false,
  accessibilityLabel,
  style,
}: Props) {
  const isDisabled = disabled || loading;

  return (
    <Pressable
      onPress={onPress}
      disabled={isDisabled}
      accessibilityRole="button"
      accessibilityLabel={accessibilityLabel ?? label}
      accessibilityState={{ disabled: isDisabled, busy: loading }}
      style={({ pressed }) => [
        styles.base,
        isDisabled && styles.disabled,
        pressed && !isDisabled && styles.pressed,
        style,
      ]}
    >
      {loading ? (
        <ActivityIndicator color={colors.ink} />
      ) : (
        <Text style={styles.label}>{label}</Text>
      )}
    </Pressable>
  );
}

const styles = StyleSheet.create({
  base: {
    minHeight: size.button,
    paddingHorizontal: 18,
    borderRadius: radius.button,
    backgroundColor: colors.lime,
    alignItems: 'center',
    justifyContent: 'center',
  },
  pressed: {
    // Subtle depress — lime darkens slightly, no colour swap.
    opacity: 0.85,
  },
  disabled: {
    opacity: 0.5,
  },
  label: {
    fontFamily: fonts.display,
    fontSize: 16,
    color: colors.ink,
    textTransform: 'uppercase',
    letterSpacing: 1.3,
  },
});
