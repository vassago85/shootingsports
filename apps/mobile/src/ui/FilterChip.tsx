import { Ionicons } from '@expo/vector-icons';
import { Pressable, StyleSheet, Text, View } from 'react-native';

import { colors, fonts, radius, size } from '@/theme';

type Props = {
  label: string;
  selected: boolean;
  onPress: () => void;
  /**
   * When the chip is a removable filter badge (e.g. "Gauteng ×" on
   * the Calendar after a Find deep link), render it as a solid
   * forest chip with an inline × icon.
   */
  removable?: boolean;
  /** Optional accessibility label override; falls back to `label`. */
  accessibilityLabel?: string;
};

/**
 * Filter chip.
 *
 * Spec: 44pt high pill. Rest: white with chipBorder. Active: forest
 * fill with page-coloured text. Uses `accessibilityState.selected`
 * so VoiceOver announces "selected" or "not selected".
 *
 * Removable variant: solid forest chip with an inline × icon. Used
 * for the removable province chip in M3c when the user arrives at
 * the Calendar from a Find map pin.
 */
export default function FilterChip({
  label,
  selected,
  onPress,
  removable = false,
  accessibilityLabel,
}: Props) {
  const activeOrRemovable = selected || removable;

  return (
    <Pressable
      onPress={onPress}
      accessibilityRole="button"
      accessibilityLabel={accessibilityLabel ?? label}
      accessibilityState={{ selected }}
      style={({ pressed }) => [
        styles.chip,
        activeOrRemovable ? styles.chipActive : styles.chipRest,
        pressed && styles.pressed,
      ]}
    >
      <View style={styles.row}>
        <Text style={activeOrRemovable ? styles.textActive : styles.textRest}>
          {label}
        </Text>
        {removable && (
          <Ionicons name="close" size={14} color={colors.page} style={styles.closeIcon} />
        )}
      </View>
    </Pressable>
  );
}

const styles = StyleSheet.create({
  chip: {
    minHeight: size.chip,
    paddingHorizontal: 14,
    borderRadius: radius.chip,
    borderWidth: 1,
    justifyContent: 'center',
  },
  chipRest: {
    backgroundColor: colors.card,
    borderColor: colors.chipBorder,
  },
  chipActive: {
    backgroundColor: colors.forest,
    borderColor: colors.forest,
  },
  pressed: {
    opacity: 0.7,
  },
  row: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 6,
  },
  textRest: {
    fontFamily: fonts.bodyMedium,
    fontSize: 14,
    color: colors.ink,
  },
  textActive: {
    fontFamily: fonts.bodyMedium,
    fontSize: 14,
    color: colors.page,
  },
  closeIcon: {
    marginLeft: 2,
  },
});
