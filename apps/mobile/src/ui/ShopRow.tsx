import { useState } from 'react';
import { Image, Pressable, StyleSheet, Text, View } from 'react-native';

import { Provider } from '@/api/providers';
import { colors, fonts, radius } from '@/theme';

type Props = {
  provider: Provider;
  onPress: () => void;
};

/**
 * Shop row — M4j Find Shops list.
 *
 * Compact single-row layout mirroring {@link RangeRow} and {@link ClubRow}:
 *
 *   [48×48 logo-or-initials] [shop name                ]
 *   [                      ] [town, province           ]
 *   [                      ] [CATEGORY · tagline       ]
 *
 * Replaces the earlier 2-column tile grid. The grid wasted vertical
 * space on a repetitive square logo region and only showed two
 * shops per row on a 390pt phone; the row layout fits 7–8 shops in
 * the same viewport while keeping the logo as the primary visual
 * anchor. Falls back to a tinted initials tile when a logo is
 * missing or 404s so the row never shows an empty square.
 */
export default function ShopRow({ provider, onPress }: Props) {
  const location = [provider.town, provider.province]
    .filter(Boolean)
    .join(', ');
  const initials = computeInitials(provider.name);
  const [logoFailed, setLogoFailed] = useState(false);
  const showLogo = provider.logo_url !== null && !logoFailed;
  // Category stays mono-uppercase (small, scanning); description
  // moves into the body font with 2-line clamp so a shooter can
  // actually read the shop's tagline inline instead of being told
  // "DEALER · FIREARMS, AMMUNITION, OPTIC…" and having to tap in.
  const category = provider.category_label?.trim().toUpperCase() ?? '';
  const description = provider.tagline?.trim() ?? '';
  const isFeatured = provider.tier === 'featured';

  return (
    <Pressable
      onPress={onPress}
      accessibilityRole="button"
      accessibilityLabel={`${provider.name}, ${provider.category_label ?? 'shop'}, ${location.length > 0 ? location : 'nationwide'}`}
      style={({ pressed }) => [
        styles.row,
        isFeatured && styles.rowFeatured,
        pressed && styles.pressed,
      ]}
    >
      {showLogo ? (
        <Image
          source={{ uri: provider.logo_url as string }}
          style={styles.logoTile}
          accessibilityIgnoresInvertColors
          resizeMode="contain"
          onError={() => setLogoFailed(true)}
        />
      ) : (
        <View style={styles.initialsTile}>
          <Text style={styles.initials}>{initials}</Text>
        </View>
      )}

      <View style={styles.body}>
        <Text style={styles.title} numberOfLines={2}>
          {provider.name}
        </Text>
        <Text style={styles.meta} numberOfLines={1}>
          {location.length > 0 ? location : 'Nationwide'}
        </Text>
        {category.length > 0 && (
          <Text style={styles.category} numberOfLines={1}>
            {category}
          </Text>
        )}
        {description.length > 0 && (
          <Text style={styles.description} numberOfLines={2}>
            {description}
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

const styles = StyleSheet.create({
  row: {
    flexDirection: 'row',
    gap: 12,
    paddingHorizontal: 16,
    paddingVertical: 10,
    alignItems: 'center',
    backgroundColor: colors.card,
    borderBottomWidth: 1,
    borderBottomColor: colors.rule,
  },
  // Featured suppliers get a subtle lime hairline on the left so
  // they stand out in a long alphabetical list without shouting.
  rowFeatured: {
    borderLeftWidth: 3,
    borderLeftColor: colors.lime,
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
  category: {
    fontFamily: fonts.monoSemi,
    fontSize: 11,
    letterSpacing: 1.2,
    color: colors.link,
  },
  /* Shop tagline — body font, 2 lines, so a real sentence fits
   * without a mid-word ellipsis. Sits beneath the mono category tag. */
  description: {
    fontFamily: fonts.body,
    fontSize: 13,
    lineHeight: 18,
    color: colors.ink,
    marginTop: 2,
  },
});
