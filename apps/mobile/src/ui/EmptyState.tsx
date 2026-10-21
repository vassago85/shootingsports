import { Ionicons } from '@expo/vector-icons';
import { ComponentProps, ReactNode } from 'react';
import { StyleSheet, Text, View } from 'react-native';

import { colors, fonts } from '@/theme';

import GhostButton from './GhostButton';

type IoniconName = ComponentProps<typeof Ionicons>['name'];

type Props = {
  /** Stroke icon drawn centred above the copy. Any Ionicons name. */
  icon?: IoniconName;
  /** Main empty-state copy — muted 15px per spec. */
  children: ReactNode;
  /** Optional single ghost-button action beneath the copy. */
  action?: {
    label: string;
    onPress: () => void;
  };
};

/**
 * Empty state.
 *
 * Spec: centred stroke icon in the control colour, muted 15px copy,
 * optional single action. Use this when a list legitimately has
 * nothing to show OR when a feature isn't yet available on this
 * surface (§7: "Every empty state says whether there is no data or
 * the feature isn't available yet"). Pass different copy to carry
 * each meaning.
 *
 * For a hard error (list failed to load) use {@link ErrorRow}
 * instead — empty states must never masquerade as errors.
 */
export default function EmptyState({ icon, children, action }: Props) {
  return (
    <View style={styles.container}>
      {icon !== undefined && (
        <Ionicons name={icon} size={48} color={colors.control} style={styles.icon} />
      )}
      <Text style={styles.copy}>{children}</Text>
      {action !== undefined && (
        <GhostButton label={action.label} onPress={action.onPress} style={styles.action} />
      )}
    </View>
  );
}

const styles = StyleSheet.create({
  container: {
    alignItems: 'center',
    justifyContent: 'center',
    paddingHorizontal: 32,
    paddingVertical: 48,
    gap: 14,
  },
  icon: {
    marginBottom: 4,
  },
  copy: {
    fontFamily: fonts.body,
    fontSize: 15,
    lineHeight: 22,
    color: colors.muted,
    textAlign: 'center',
  },
  action: {
    marginTop: 6,
    alignSelf: 'center',
  },
});
