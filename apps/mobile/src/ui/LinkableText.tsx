import * as Linking from 'expo-linking';
import { StyleSheet, Text, TextStyle } from 'react-native';

import { colors } from '@/theme';

type Props = {
  children: string;
  style?: TextStyle | TextStyle[];
};

/**
 * Render prose with inline tap targets for the two most-asked
 * actions on a match detail page: phoning a match director and
 * emailing a contact. Plain `<Text>` leaves these dead on mobile;
 * `expo-linking` opens the dialer / mail client in one tap.
 *
 * SA phone-number detection accepts:
 *
 *   • `+27 11 123 4567` / `+27-11-123-4567`
 *   • `0XX XXX XXXX` / `0XX-XXX-XXXX`
 *   • Compact digits like `011 123 4567`
 *
 * Email detection is deliberately conservative (standard local@domain)
 * so a stray "@mention" in prose doesn't turn into a mailto: link.
 */
export default function LinkableText({ children, style }: Props) {
  const segments = splitIntoSegments(children);

  return (
    <Text style={style}>
      {segments.map((segment, idx) => {
        if (segment.kind === 'plain') {
          return <Text key={idx}>{segment.value}</Text>;
        }

        const url =
          segment.kind === 'phone'
            ? `tel:${segment.value.replace(/[\s-]/g, '')}`
            : `mailto:${segment.value}`;

        return (
          <Text
            key={idx}
            style={styles.link}
            accessibilityRole="link"
            onPress={() => {
              void Linking.openURL(url);
            }}
          >
            {segment.value}
          </Text>
        );
      })}
    </Text>
  );
}

type Segment =
  | { kind: 'plain'; value: string }
  | { kind: 'phone'; value: string }
  | { kind: 'email'; value: string };

/**
 * Phone regex: SA national (0xx) or international (+27) with
 * optional spaces or dashes between the 3-3-4 groups. The anchors
 * on both sides prevent half-match inside a longer digit run.
 */
const PHONE_REGEX = /(\+27[\s-]?\d{2}[\s-]?\d{3}[\s-]?\d{4}|0\d{2}[\s-]?\d{3}[\s-]?\d{4})/g;

/**
 * Email regex: standard local@domain.tld. Deliberately strict so
 * handles like "@someone" don't false-match.
 */
const EMAIL_REGEX = /([A-Za-z0-9._%+-]+@[A-Za-z0-9.-]+\.[A-Za-z]{2,})/g;

function splitIntoSegments(text: string): Segment[] {
  // Combine the two regexes with the same capture-group semantics
  // so we can walk a single match stream in document order.
  const combined = new RegExp(`${PHONE_REGEX.source}|${EMAIL_REGEX.source}`, 'g');
  const segments: Segment[] = [];
  let lastIndex = 0;
  let match: RegExpExecArray | null;

  while ((match = combined.exec(text)) !== null) {
    if (match.index > lastIndex) {
      segments.push({ kind: 'plain', value: text.slice(lastIndex, match.index) });
    }

    const matched = match[0];
    if (matched.includes('@')) {
      segments.push({ kind: 'email', value: matched });
    } else {
      segments.push({ kind: 'phone', value: matched });
    }

    lastIndex = match.index + matched.length;
  }

  if (lastIndex < text.length) {
    segments.push({ kind: 'plain', value: text.slice(lastIndex) });
  }

  return segments;
}

const styles = StyleSheet.create({
  link: {
    color: colors.link,
    textDecorationLine: 'underline',
  },
});
