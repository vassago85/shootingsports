import { StyleSheet, Text, View } from 'react-native';

import { colors, fonts, radius } from '@/theme';

type Plan = 'free' | 'pro' | 'trial';

type Props = {
  plan: Plan;
  /** Required when `plan === 'trial'`. Shown in the pill text. */
  trialDaysRemaining?: number;
  /**
   * Where the pill sits. On a forest band we use lime; on a light
   * surface we fall back to link-green so the pill is readable
   * against the page / card background.
   */
  onForest?: boolean;
};

/**
 * Plan pill — shown next to the shooter's name on the Me hero and on
 * the website nav.
 *
 * Spec: bordered pill, mono 12 uppercase. "Free", "Pro" or
 * "Trial · N days left". On forest: lime border and text, or lime
 * fill with forest text for Pro. On light surfaces we substitute
 * link-green for lime so the pill still has 4.5:1 contrast against
 * the page.
 */
export default function PlanPill({ plan, trialDaysRemaining, onForest = false }: Props) {
  const label = planLabel(plan, trialDaysRemaining);
  const style = pillStyle(plan, onForest);
  const textStyle = pillTextStyle(plan, onForest);

  return (
    <View style={style}>
      <Text style={textStyle}>{label}</Text>
    </View>
  );
}

function planLabel(plan: Plan, trialDaysRemaining: number | undefined): string {
  switch (plan) {
    case 'free':
      return 'Free';
    case 'pro':
      return 'Pro';
    case 'trial':
      return `Trial · ${trialDaysRemaining ?? 0} days left`;
    default: {
      // Exhaustiveness: if a new plan is added to the union, this
      // compile-fails until the switch is updated.
      const _never: never = plan;
      void _never;
      return '';
    }
  }
}

function pillStyle(plan: Plan, onForest: boolean) {
  if (plan === 'pro') {
    return onForest ? styles.proOnForest : styles.proOnLight;
  }

  return onForest ? styles.outlinedOnForest : styles.outlinedOnLight;
}

function pillTextStyle(plan: Plan, onForest: boolean) {
  if (plan === 'pro') {
    return onForest ? styles.textProOnForest : styles.textProOnLight;
  }

  return onForest ? styles.textOnForest : styles.textOnLight;
}

const base = {
  paddingHorizontal: 10,
  paddingVertical: 4,
  borderRadius: radius.chip,
  borderWidth: 1,
} as const;

const baseText = {
  fontFamily: fonts.monoSemi,
  fontSize: 12,
  letterSpacing: 1.1,
  textTransform: 'uppercase',
} as const;

const styles = StyleSheet.create({
  outlinedOnForest: { ...base, borderColor: colors.lime, backgroundColor: 'transparent' },
  outlinedOnLight: { ...base, borderColor: colors.link, backgroundColor: 'transparent' },
  proOnForest: { ...base, borderColor: colors.lime, backgroundColor: colors.lime },
  proOnLight: { ...base, borderColor: colors.link, backgroundColor: colors.link },
  textOnForest: { ...baseText, color: colors.lime },
  textOnLight: { ...baseText, color: colors.link },
  textProOnForest: { ...baseText, color: colors.forest },
  textProOnLight: { ...baseText, color: colors.onForest },
});
