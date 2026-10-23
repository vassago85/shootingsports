import { Pressable, StyleSheet, Text, View } from 'react-native';

import { splitMatchDate } from '@/api/dates';
import { EventCard } from '@/api/events';
import { colors, fonts } from '@/theme';

type Props = {
  event: EventCard;
  onPress: () => void;
};

/**
 * Match row — the dense Calendar list row.
 *
 * Spec layout:
 *
 *   [52×56 tint date block] [title                 ]
 *   [day in Saira        ] [range · province       ]
 *   [month in mono link  ] [host · discipline      ]
 *
 * Multi-day same-month matches get a "7–8" range in the day cell so
 * users don't plan for the wrong day. The whole row is one tap
 * target. For a less dense, more marketing-feeling card use
 * {@link MatchCard}.
 */
export default function MatchRow({ event, onPress }: Props) {
  const { day, month } = splitMatchDate(event.starts_at, event.ends_at);
  // Give the date block a hair more breathing room for the "7–8"
  // range glyph compared to a single digit.
  const isRange = day.includes('–');
  const location = event.location_label ?? '';
  // Defensive chaining: `disciplines` is marked `whenLoaded` in the
  // API Resource, so a legacy row or a controller path that forgets
  // to eager-load drops the whole key. Without this, one such event
  // crashes the entire Calendar render.
  const discipline = event.disciplines?.[0]?.name ?? null;
  // Prefer the short organisation name on the dense list row so a
  // long federation name doesn't push the discipline off the line.
  const hostName =
    event.host?.organisation?.short_name ?? event.host?.name ?? '';
  const hostAndDiscipline = discipline !== null
    ? `${hostName} · ${discipline}`
    : hostName;

  const accessibilityDate = event.starts_at !== null
    ? new Date(event.starts_at).toDateString()
    : 'date to be confirmed';

  return (
    <Pressable
      onPress={onPress}
      accessibilityRole="button"
      accessibilityLabel={`${event.title}, ${accessibilityDate}, ${location}, ${hostAndDiscipline}`}
      style={({ pressed }) => [styles.row, pressed && styles.pressed]}
    >
      <View style={styles.dateBlock}>
        <Text style={isRange ? styles.dayRange : styles.day}>{day}</Text>
        <Text style={styles.month}>{month}</Text>
      </View>

      <View style={styles.body}>
        <Text style={styles.title} numberOfLines={2}>
          {event.title}
        </Text>
        <Text style={styles.meta} numberOfLines={1}>
          {location}
        </Text>
        <Text style={styles.meta} numberOfLines={1}>
          {hostAndDiscipline}
        </Text>
      </View>
    </Pressable>
  );
}

const styles = StyleSheet.create({
  row: {
    flexDirection: 'row',
    gap: 12,
    paddingHorizontal: 16,
    paddingVertical: 12,
    backgroundColor: colors.card,
    borderBottomWidth: 1,
    borderBottomColor: colors.rule,
  },
  pressed: {
    backgroundColor: colors.tint,
  },
  dateBlock: {
    width: 52,
    height: 56,
    borderRadius: 6,
    backgroundColor: colors.tint,
    alignItems: 'center',
    justifyContent: 'center',
  },
  day: {
    fontFamily: fonts.display,
    fontSize: 22,
    color: colors.ink,
    lineHeight: 24,
  },
  // Smaller glyph for "7–8" so the range fits inside the 52pt block.
  dayRange: {
    fontFamily: fonts.display,
    fontSize: 17,
    color: colors.ink,
    lineHeight: 20,
  },
  month: {
    fontFamily: fonts.monoSemi,
    fontSize: 11,
    color: colors.link,
    letterSpacing: 1.2,
  },
  body: {
    flex: 1,
    gap: 2,
    justifyContent: 'center',
  },
  title: {
    fontFamily: fonts.bodySemi,
    fontSize: 15,
    color: colors.ink,
    lineHeight: 20,
  },
  meta: {
    fontFamily: fonts.mono,
    fontSize: 12,
    color: colors.muted,
  },
});
