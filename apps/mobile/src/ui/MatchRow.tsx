import { Pressable, StyleSheet, Text, View } from 'react-native';

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
 * The whole row is one tap target. For a less dense, more
 * marketing-feeling card use {@link MatchCard}.
 */
export default function MatchRow({ event, onPress }: Props) {
  const { day, month } = splitDate(event.starts_at);
  const location = event.location_label ?? '';
  // Defensive chaining: `disciplines` is marked `whenLoaded` in the
  // API Resource, so a legacy row or a controller path that forgets
  // to eager-load drops the whole key. Without this, one such event
  // crashes the entire Calendar render.
  const discipline = event.disciplines?.[0]?.name ?? null;
  const hostName = event.host?.name ?? '';
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
        <Text style={styles.day}>{day}</Text>
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

/**
 * ISO → `{ day: '25', month: 'OCT' }`. "TBC" when the event has no
 * confirmed start date.
 */
function splitDate(iso: string | null): { day: string; month: string } {
  if (iso === null) {
    return { day: '—', month: 'TBC' };
  }

  const date = new Date(iso);
  return {
    day: String(date.getDate()),
    month: date.toLocaleDateString('en-ZA', { month: 'short' }).toUpperCase(),
  };
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
