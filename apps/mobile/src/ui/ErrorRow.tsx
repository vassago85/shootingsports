import { Ionicons } from '@expo/vector-icons';
import { ReactNode } from 'react';
import { StyleSheet, Text, View } from 'react-native';

import { colors, fonts } from '@/theme';

type Props = {
  children: ReactNode;
};

/**
 * Inline error row.
 *
 * Spec: errorBg background, errorBorder border, error text, an alert
 * icon on the left, `role="alert"` / `accessibilityLiveRegion="polite"`
 * so screen readers announce the message when it appears.
 *
 * Sits above the fields it refers to (e.g. above a sign-in form) or
 * above a list that failed to load. For full-screen error states use
 * {@link EmptyState} instead.
 */
export default function ErrorRow({ children }: Props) {
  return (
    <View
      style={styles.row}
      accessibilityRole="alert"
      accessibilityLiveRegion="polite"
    >
      <Ionicons
        name="alert-circle"
        size={18}
        color={colors.error}
        style={styles.icon}
      />
      <Text style={styles.text}>{children}</Text>
    </View>
  );
}

const styles = StyleSheet.create({
  row: {
    flexDirection: 'row',
    alignItems: 'flex-start',
    gap: 10,
    paddingVertical: 10,
    paddingHorizontal: 12,
    borderRadius: 8,
    borderWidth: 1,
    borderColor: colors.errorBorder,
    backgroundColor: colors.errorBg,
  },
  icon: {
    marginTop: 1,
  },
  text: {
    flex: 1,
    fontFamily: fonts.body,
    fontSize: 14,
    color: colors.error,
    lineHeight: 20,
  },
});
