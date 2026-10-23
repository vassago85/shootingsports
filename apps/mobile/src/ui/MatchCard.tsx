import { Pressable, StyleSheet, Text } from 'react-native';

import { formatMatchDate } from '@/api/dates';
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
  const date = formatMatchDate(event.starts_at, event.ends_at);
  const location = event.location_label;
  const meta = `${date} · ${location}`;
  // Prefer the organisation short_name ("SAPRF") over the full
  // host name ("South African Precision Rifle Federation") so the
  // host line doesn't truncate on a card width. Falls back to the
  // full name when short_name is missing.
  const hostLabel = event.host.organisation?.short_name ?? event.host.name;

  return (
    <Pressable
      onPress={onPress}
      accessibilityRole="button"
      accessibilityLabel={`${event.title}. ${meta}. Host ${hostLabel}.`}
      style={({ pressed }) => [styles.card, pressed && styles.pressed]}
    >
      <Text style={styles.title} numberOfLines={2}>
        {event.title}
      </Text>
      <Text style={styles.meta} numberOfLines={1}>
        {meta}
      </Text>
      <Text style={styles.host} numberOfLines={1}>
        {hostLabel}
      </Text>
    </Pressable>
  );
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
