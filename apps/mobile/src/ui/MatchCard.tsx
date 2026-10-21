import { Pressable, StyleSheet, Text, View } from 'react-native';

import { EventCard } from '@/api/events';
import { colors, fonts, radius } from '@/theme';

type Props = {
  event: EventCard;
  onPress: () => void;
};

/**
 * Match card.
 *
 * Spec: white card, 1px rule border, radius 14. Title (Plex 600 16),
 * meta line `date · range, province`, host line. The whole card is
 * one tap target.
 *
 * Used on M2 Home (list of 6) and on the saved-matches list on M5.
 * For the dense Calendar list use {@link MatchRow} instead.
 */
export default function MatchCard({ event, onPress }: Props) {
  const date = formatCardDate(event.starts_at);
  const location = event.location_label;
  const meta = `${date} · ${location}`;

  return (
    <Pressable
      onPress={onPress}
      accessibilityRole="button"
      accessibilityLabel={`${event.title}. ${meta}. Host ${event.host.name}.`}
      style={({ pressed }) => [styles.card, pressed && styles.pressed]}
    >
      <Text style={styles.title} numberOfLines={2}>
        {event.title}
      </Text>
      <Text style={styles.meta} numberOfLines={1}>
        {meta}
      </Text>
      <Text style={styles.host} numberOfLines={1}>
        {event.host.name}
      </Text>
    </Pressable>
  );
}

/**
 * ISO → "SAT 25 OCT" style used in the cards. Falls back to "TBC"
 * when the event has no confirmed start date yet.
 */
function formatCardDate(iso: string | null): string {
  if (iso === null) {
    return 'TBC';
  }

  const date = new Date(iso);
  const weekday = date.toLocaleDateString('en-ZA', { weekday: 'short' }).toUpperCase();
  const day = date.getDate();
  const month = date.toLocaleDateString('en-ZA', { month: 'short' }).toUpperCase();

  return `${weekday} ${day} ${month}`;
}

const styles = StyleSheet.create({
  card: {
    backgroundColor: colors.card,
    borderRadius: radius.card,
    borderWidth: 1,
    borderColor: colors.rule,
    padding: 16,
    gap: 6,
  },
  pressed: {
    backgroundColor: colors.tint,
  },
  title: {
    fontFamily: fonts.bodySemi,
    fontSize: 16,
    color: colors.ink,
    lineHeight: 22,
  },
  meta: {
    fontFamily: fonts.mono,
    fontSize: 13,
    color: colors.muted,
  },
  host: {
    fontFamily: fonts.body,
    fontSize: 13,
    color: colors.muted,
  },
});
