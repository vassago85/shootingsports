import { Pressable, StyleSheet, Text, View } from 'react-native';

import { Venue } from '@/api/venues';
import { colors, fonts, radius } from '@/theme';

type Props = {
  venue: Venue;
  onPress: () => void;
};

/**
 * Range row — M4h Find Ranges list.
 *
 *   [48×48 initials tile] [range name                  ]
 *   [                   ] [town, province              ]
 *   [                   ] [disciplines · N upcoming    ]
 *
 * No distance line (deferred to a future revision that opts into
 * geolocation). The row is one tap target leading to the website
 * range page in an in-app browser (handled by the caller).
 */
export default function RangeRow({ venue, onPress }: Props) {
  const initials = computeInitials(venue.name);
  const location = [venue.town, venue.province].filter(Boolean).join(', ');
  const disciplineLine = buildDisciplineLine(venue);

  return (
    <Pressable
      onPress={onPress}
      accessibilityRole="button"
      accessibilityLabel={`${venue.name}, ${location}, ${disciplineLine}`}
      style={({ pressed }) => [styles.row, pressed && styles.pressed]}
    >
      <View style={styles.initialsTile}>
        <Text style={styles.initials}>{initials}</Text>
      </View>

      <View style={styles.body}>
        <Text style={styles.title} numberOfLines={2}>
          {venue.name}
        </Text>
        <Text style={styles.meta} numberOfLines={1}>
          {location.length > 0 ? location : 'Location to be confirmed'}
        </Text>
        <Text style={styles.meta} numberOfLines={1}>
          {disciplineLine}
        </Text>
      </View>
    </Pressable>
  );
}

/**
 * First-letter-of-each-word, up to two letters, uppercased.
 */
function computeInitials(name: string): string {
  const words = name.trim().split(/\s+/).filter(Boolean);
  if (words.length === 0) {
    return '?';
  }
  const letters = words.slice(0, 2).map((w) => w.charAt(0).toUpperCase());
  return letters.join('');
}

function buildDisciplineLine(venue: Venue): string {
  const names = venue.disciplines.map((d) => d.name);
  const prefix = names.length === 0 ? '' : names.slice(0, 2).join(' · ');
  const suffix =
    venue.upcoming_count > 0
      ? `${venue.upcoming_count} upcoming`
      : 'No upcoming matches';

  if (prefix === '') {
    return suffix;
  }
  if (names.length > 2) {
    return `${prefix} +${names.length - 2} · ${suffix}`;
  }
  return `${prefix} · ${suffix}`;
}

const styles = StyleSheet.create({
  row: {
    flexDirection: 'row',
    gap: 12,
    paddingHorizontal: 16,
    paddingVertical: 10,
    alignItems: 'center',
  },
  pressed: {
    opacity: 0.7,
  },
  initialsTile: {
    width: 48,
    height: 48,
    borderRadius: radius.card,
    backgroundColor: colors.tint,
    alignItems: 'center',
    justifyContent: 'center',
  },
  initials: {
    fontFamily: fonts.display,
    fontSize: 16,
    color: colors.link,
    letterSpacing: 0.6,
  },
  body: {
    flex: 1,
    gap: 2,
  },
  title: {
    fontFamily: fonts.bodySemi,
    fontSize: 16,
    color: colors.ink,
  },
  meta: {
    fontFamily: fonts.body,
    fontSize: 13,
    color: colors.muted,
  },
});
