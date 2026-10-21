// apps/mobile theme. Values match design/tokens.json and the live shootingsports.co.za site.
// Read real insets from react-native-safe-area-context at runtime; never hard-code 59/34.
export const colors = {
  forest: '#0E3328',
  lime: '#C5E07A',
  ink: '#10251F',
  page: '#F7F8F5',
  card: '#FFFFFF',
  rule: '#DCE3DE',
  chipBorder: '#D6DAD8',
  control: '#C6CCC9',
  muted: '#52655E',
  placeholder: '#6B7C74',
  link: '#245D3F',
  tint: '#E7EFE8',
  error: '#B3261E',
  errorBg: '#FBEAEA',
  errorBorder: '#EBC1BE',
  onForest: '#FFFFFF',
  onForestMuted: '#C6CCC9',
} as const;

// Load with @expo-google-fonts/saira-condensed, /ibm-plex-sans, /ibm-plex-mono.
export const fonts = {
  display: 'SairaCondensed_700Bold',
  displaySemi: 'SairaCondensed_600SemiBold',
  body: 'IBMPlexSans_400Regular',
  bodyMedium: 'IBMPlexSans_500Medium',
  bodySemi: 'IBMPlexSans_600SemiBold',
  mono: 'IBMPlexMono_400Regular',
  monoSemi: 'IBMPlexMono_600SemiBold',
} as const;

export const radius = { card: 14, button: 10, input: 10, chip: 999 } as const;
export const size = { touchMin: 44, chip: 44, button: 48, input: 52, tabBar: 49 } as const;
export const space = { screenX: 20, section: 26, cardPad: 16, list: 10 } as const;
