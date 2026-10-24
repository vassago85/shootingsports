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
 * a date line, a location line, and a host line.
 *
 * The location line uses the server's `location_label` ("Rayton · GP"
 * / "GP" / "Venue TBC") rather than concatenating the venue name
 * with a long province label. Earlier builds rendered a `date · {range}, {province}`
 * one-liner that truncated the province to things like "Legends
 * Adventure Farm, Gaute…" on narrow cards — the single most useful
 * token on the card (which province) was the one being chopped.
 * Allowing the location line to wrap to two lines means the
 * province is always readable even on the longest venue names.
 *
 * Used on M2 Home carousels and on the saved-matches list on M5.
 * For the dense Calendar list use {@link MatchRow} instead.
 */
export default function MatchCard({ event, onPress }: Props) {
  const date = formatMatchDate(event.starts_at, event.ends_at);
  const location = event.location_label;
  // Prefer the organisation short_name ("SAPRF") over the full
  // host name ("South African Precision Rifle Federation") so the
  // host line doesn't truncate on a card width. Falls back to the
  // full name when short_name is missing.
  const hostLabel = event.host.organisation?.short_name ?? event.host.name;

  return (
    <Pressable
      onPress={onPress}
      accessibilityRole="button"
      accessibilityLabel={`${event.title}. ${date}, ${location}. Host ${hostLabel}.`}
      style={({ pressed }) => [styles.card, pressed && styles.pressed]}
    >
      <Text style={styles.title} numberOfLines={2}>
        {event.title}
      </Text>
      <Text style={styles.meta} numberOfLines={1}>
        {date}
      </Text>
      <Text style={styles.meta} numberOfLines={2}>
        {location}
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
