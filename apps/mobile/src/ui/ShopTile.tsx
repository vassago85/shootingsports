import { Image, Pressable, StyleSheet, Text, View } from 'react-native';

import { Provider } from '@/api/providers';
import { colors, fonts, radius } from '@/theme';

type Props = {
  provider: Provider;
  onPress: () => void;
};

/**
 * Shop tile for M4j — 2-column grid, square logo-first.
 *
 * When a shop has no logo (common while the directory is still
 * being populated) we fall back to a tinted initials tile with the
 * category label. Looks intentional, no "broken image" feel.
 *
 * Featured-tier shops get a subtle lime hairline border so
 * sponsors visually surface above the base tier without shouting.
 */
export default function ShopTile({ provider, onPress }: Props) {
  const location = [provider.town, provider.province].filter(Boolean).join(', ');
  const initials = computeInitials(provider.name);
  const isFeatured = provider.tier === 'featured';

  return (
    <Pressable
      onPress={onPress}
      accessibilityRole="button"
      accessibilityLabel={`${provider.name}, ${provider.category_label ?? 'shop'}, ${location}`}
      style={({ pressed }) => [
        styles.tile,
        isFeatured && styles.tileFeatured,
        pressed && styles.pressed,
      ]}
    >
      {provider.logo_url !== null ? (
        <Image
          source={{ uri: provider.logo_url }}
          style={styles.logo}
          accessibilityIgnoresInvertColors
          resizeMode="contain"
        />
      ) : (
        <View style={styles.initialsSquare}>
          <Text style={styles.initials}>{initials}</Text>
          {provider.category_label !== null && (
            <Text style={styles.initialsCategory} numberOfLines={1}>
              {provider.category_label}
            </Text>
          )}
        </View>
      )}

      <Text style={styles.name} numberOfLines={2}>
        {provider.name}
      </Text>
      <Text style={styles.meta} numberOfLines={1}>
        {location.length > 0 ? location : 'Nationwide'}
      </Text>
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
  tile: {
    flex: 1,
    padding: 10,
    borderRadius: radius.card,
    backgroundColor: colors.card,
    borderWidth: 1,
    borderColor: colors.rule,
    gap: 8,
  },
  tileFeatured: {
    borderColor: colors.lime,
  },
  pressed: {
    opacity: 0.85,
  },

  /* Square logo region — fixed aspect so the grid stays regular even
   * when logos have wildly different intrinsic ratios. */
  logo: {
    width: '100%',
    aspectRatio: 1,
    backgroundColor: colors.card,
  },
  initialsSquare: {
    width: '100%',
    aspectRatio: 1,
    borderRadius: radius.card,
    backgroundColor: colors.tint,
    alignItems: 'center',
    justifyContent: 'center',
    gap: 4,
  },
  initials: {
    fontFamily: fonts.display,
    fontSize: 32,
    color: colors.link,
    letterSpacing: 1,
  },
  initialsCategory: {
    fontFamily: fonts.monoSemi,
    fontSize: 10,
    letterSpacing: 1.2,
    textTransform: 'uppercase',
    color: colors.muted,
    paddingHorizontal: 6,
  },

  name: {
    fontFamily: fonts.bodySemi,
    fontSize: 14,
    color: colors.ink,
    lineHeight: 18,
  },
  meta: {
    fontFamily: fonts.body,
    fontSize: 12,
    color: colors.muted,
  },
});
