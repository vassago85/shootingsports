import { useState } from 'react';
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
 * The Visitors pill tails the name only when the club has
 * explicitly opted in (`visitors_welcome === true`). A null
 * value — the "not asked yet" state new rows start in — keeps the
 * pill hidden so the badge carries a meaningful "yes" signal.
 *
 * When the server returns a logo_url that 404s, the row falls back
 * to the initials tile on image error instead of leaving a blank
 * square where the avatar should be.
 */
export default function ClubRow({ organisation, onPress }: Props) {
  const location = [organisation.town, organisation.province]
    .filter(Boolean)
    .join(', ');
  const disciplineLine = buildDisciplineLine(organisation);
  const initials = computeInitials(organisation.name);
  const [logoFailed, setLogoFailed] = useState(false);
  const showLogo = organisation.logo_url !== null && !logoFailed;
  // National bodies (federations, country-wide associations) never
  // show the Visitors pill — the concept applies to individual clubs,
  // not umbrella orgs. For every other row the pill shows only when
  // the admin has explicitly ticked `visitors_welcome`.
  const isNational = organisation.group === 'national';
  const showsVisitorsPill =
    !isNational && organisation.visitors_welcome === true;
  // Meta line fallback:
  //   - national body with a HQ town  -> "Mossel Bay, Western Cape"
  //   - national body with no town    -> "National body"
  //   - regional club with a town     -> "Pretoria, Gauteng"
  //   - regional club with no town    -> "Location to be confirmed"
  // The empty-national fallback carries real information (this is a
  // country-wide federation, not a missing-data row) so we don't want
  // to lump it with the "unknown" copy a regional club uses.
  let metaLine: string;
  if (location.length > 0) {
    metaLine = location;
  } else if (isNational) {
    metaLine = 'National body';
  } else {
    metaLine = 'Location to be confirmed';
  }

  return (
    <Pressable
      onPress={onPress}
      accessibilityRole="button"
      accessibilityLabel={`${organisation.name}, ${location}, ${disciplineLine}${
        showsVisitorsPill ? ', visitors welcome' : ''
      }`}
      style={({ pressed }) => [styles.row, pressed && styles.pressed]}
    >
      {showLogo ? (
        <Image
          source={{ uri: organisation.logo_url as string }}
          style={styles.logoTile}
          // `contain` so wordmarks (CTSASA, SAPRF) are never cropped
          // or stretched on the 48pt tile. Keeps the padded light
          // background from `styles.logoTile` visible around the mark.
          resizeMode="contain"
          accessibilityIgnoresInvertColors
          onError={() => setLogoFailed(true)}
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
          {showsVisitorsPill && (
            <View style={styles.visitorsPill}>
              <Text style={styles.visitorsPillText}>VISITORS</Text>
            </View>
          )}
        </View>
        <Text style={styles.meta} numberOfLines={1}>
          {metaLine}
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
