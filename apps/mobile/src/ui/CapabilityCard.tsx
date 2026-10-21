import { Pressable, StyleSheet, Text, View } from 'react-native';

import { CapabilityCard as CapabilityCardPayload } from '@/api/me';
import { colors, fonts, radius } from '@/theme';

import PrimaryButton from './PrimaryButton';

type Props = {
  card: CapabilityCardPayload;
  /**
   * Called with the matching item's `url` when the user taps a row.
   * Caller is responsible for opening it in the in-app browser
   * (expo-web-browser).
   */
  onPressItem?: (url: string) => void;
  /** Called with `card.primary_action.url` when the user taps the primary button. */
  onPressAction: (url: string) => void;
};

/**
 * Capability card — the Manage hub tile.
 *
 * Spec: white card. Eyebrow (kind), title (entity name), one-line
 * summary, up to 3 items (label left, mono meta right, rule between),
 * primary button.
 *
 * Spec open question §8.1: what each card's items should actually
 * show for Club, Shop, Range, and Media. Until that lands we render
 * the first 3 items from the generic
 * `App\Support\DeskCapabilities::for()` payload: an entity name on
 * the left, nothing on the right. When the item backend adds a
 * meta string, this component will show it in mono on the right
 * without any further change.
 */
export default function CapabilityCard({ card, onPressItem, onPressAction }: Props) {
  const items = card.items.slice(0, 3);

  return (
    <View style={styles.card}>
      <Text style={styles.eyebrow}>{card.kind}</Text>
      <Text style={styles.title}>{card.title}</Text>
      <Text style={styles.summary}>{card.summary}</Text>

      {items.length > 0 && (
        <View style={styles.itemList}>
          {items.map((item, index) => (
            <Pressable
              key={item.name}
              onPress={onPressItem !== undefined ? () => onPressItem(item.url) : undefined}
              disabled={onPressItem === undefined}
              accessibilityRole={onPressItem !== undefined ? 'link' : undefined}
              accessibilityLabel={item.name}
              style={({ pressed }) => [
                styles.item,
                index < items.length - 1 && styles.itemRule,
                pressed && onPressItem !== undefined && styles.itemPressed,
              ]}
            >
              <Text style={styles.itemLabel} numberOfLines={1}>
                {item.name}
              </Text>
            </Pressable>
          ))}
        </View>
      )}

      <PrimaryButton
        label={card.primary_action.label}
        onPress={() => onPressAction(card.primary_action.url)}
        style={styles.action}
      />
    </View>
  );
}

const styles = StyleSheet.create({
  card: {
    backgroundColor: colors.card,
    borderRadius: radius.card,
    borderWidth: 1,
    borderColor: colors.rule,
    padding: 16,
    gap: 8,
  },
  eyebrow: {
    fontFamily: fonts.monoSemi,
    fontSize: 11,
    color: colors.link,
    letterSpacing: 1.6,
    textTransform: 'uppercase',
  },
  title: {
    fontFamily: fonts.display,
    fontSize: 22,
    color: colors.ink,
    textTransform: 'uppercase',
    letterSpacing: 0.5,
  },
  summary: {
    fontFamily: fonts.body,
    fontSize: 14,
    color: colors.muted,
    lineHeight: 20,
  },
  itemList: {
    marginTop: 8,
    marginBottom: 4,
  },
  item: {
    paddingVertical: 10,
  },
  itemRule: {
    borderBottomWidth: 1,
    borderBottomColor: colors.rule,
  },
  itemPressed: {
    opacity: 0.6,
  },
  itemLabel: {
    fontFamily: fonts.bodyMedium,
    fontSize: 15,
    color: colors.ink,
  },
  action: {
    marginTop: 6,
    alignSelf: 'flex-start',
  },
});
