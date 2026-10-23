import { Image, Pressable, StyleSheet, Text, View } from 'react-native';

import { Organisation } from '@/api/organisations';
import { colors, fonts, radius } from '@/theme';

type Props = {
  organisation: Organisation;
  onPress: () => void;
};

/**
 * Club row — M4f Find Clubs list.
 *
 *   [48×48 logo-or-initials] [club name              ]
 *   [                      ] [town, province         ]
 *   [                      ] [disciplines (3 max)    ]
 *
 * If the club has set `visitors_welcome` a lime "VISITORS" pill
 * tails the name — this is the single most-asked newcomer question
 * ("can I just rock up?") and surfacing it on the row saves a tap
 * to the club page.
 */
export default function ClubRow({ organisation, onPress }: Props) {
  const location = [organisation.town, organisation.province]
    .filter(Boolean)
    .join(', ');
  const disciplineLine = buildDisciplineLine(organisation);
  const initials = computeInitials(organisation.name);

  return (
    <Pressable
      onPress={onPress}
      accessibilityRole="button"
      accessibilityLabel={`${organisation.name}, ${location}, ${disciplineLine}${
        organisation.visitors_welcome ? ', visitors welcome' : ''
      }`}
      style={({ pressed }) => [styles.row, pressed && styles.pressed]}
    >
      {organisation.logo_url !== null ? (
        <Image
          source={{ uri: organisation.logo_url }}
          style={styles.logoTile}
          accessibilityIgnoresInvertColors
        />
      ) : (
        <View style={styles.initialsTile}>
          <Text style={styles.initials}>{initials}</Text>
        </View>
      )}

      <View style={styles.body}>
        <View style={styles.titleRow}>
          <Text style={styles.title} numberOfLines={2}>
            {organisation.name}
          </Text>
          {organisation.visitors_welcome && (
            <View style={styles.visitorsPill}>
              <Text style={styles.visitorsPillText}>VISITORS</Text>
            </View>
          )}
        </View>
        <Text style={styles.meta} numberOfLines={1}>
          {location.length > 0 ? location : 'Location to be confirmed'}
        </Text>
        {disciplineLine.length > 0 && (
          <Text style={styles.meta} numberOfLines={1}>
            {disciplineLine}
          </Text>
        )}
      </View>
    </Pressable>
  );
}

function computeInitials(name: string): string {
  const words = name.trim().split(/\s+/).filter(Boolean);
  if (words.length === 0) {
    return '?';
  }
  return words
    .slice(0, 2)
    .map((w) => w.charAt(0).toUpperCase())
    .join('');
}

function buildDisciplineLine(organisation: Organisation): string {
  const names = organisation.disciplines.map((d) => d.name);
  if (names.length === 0) {
    return '';
  }
  if (names.length <= 3) {
    return names.join(' · ');
  }
  return `${names.slice(0, 3).join(' · ')} +${names.length - 3}`;
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
  logoTile: {
    width: 48,
    height: 48,
    borderRadius: radius.card,
    backgroundColor: colors.card,
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
  titleRow: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 8,
  },
  title: {
    flex: 1,
    fontFamily: fonts.bodySemi,
    fontSize: 16,
    color: colors.ink,
  },
  visitorsPill: {
    paddingHorizontal: 7,
    paddingVertical: 2,
    borderRadius: 999,
    backgroundColor: colors.lime,
  },
  visitorsPillText: {
    fontFamily: fonts.monoSemi,
    fontSize: 9,
    letterSpacing: 1.1,
    color: colors.ink,
  },
  meta: {
    fontFamily: fonts.body,
    fontSize: 13,
    color: colors.muted,
  },
});
