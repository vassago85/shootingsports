/**
 * Mobile theme.
 *
 * Source of truth: {@link ../../../docs/build-pack/design/tokens.json}
 * — these values are mirrored here because mobile code can't import
 * from outside its own package. If a token changes in tokens.json,
 * change it here too. Website mirrors live in
 * `docs/build-pack/design/tokens.css` and should land as the
 * `:root` variables in `resources/css/app.css` during Phase 1 web.
 *
 * The palette is the live shootingsports.co.za palette (forest + lime
 * light theme). The old brass + gunmetal dark palette this file
 * previously held is deliberately gone — see
 * `docs/build-pack/BUILD_SPEC.md` §2 for the rationale.
 */

/* -------------------------------------------------------------------
 * Colours
 * ---------------------------------------------------------------- */

export const colors = {
  /** Header band, hero, tab bar, upsell card, active chip, map pins */
  forest: '#0E3328',
  /** Primary button fill, active tab, eyebrow + pill on forest. Never on light bg. */
  lime: '#C5E07A',
  /** Text on light bg; text on lime */
  ink: '#10251F',
  /** Screen background */
  page: '#F7F8F5',
  /** Cards, lists, chip strip, inputs */
  card: '#FFFFFF',
  /** Card borders, dividers */
  rule: '#DCE3DE',
  /** Resting chip border */
  chipBorder: '#D6DAD8',
  /** Input borders, empty-state icons, spinner track, muted text on forest */
  control: '#C6CCC9',
  /** Meta and supporting text on light */
  muted: '#52655E',
  /** Input placeholder text */
  placeholder: '#6B7C74',
  /** Links, ghost buttons, section underline, eyebrow on light, spinner head */
  link: '#245D3F',
  /** Date blocks, icon tiles, map backdrop */
  tint: '#E7EFE8',
  /** Error text and error input border (proposed in spec; not final) */
  error: '#B3261E',
  /** Error row background (proposed) */
  errorBg: '#FBEAEA',
  /** Error row border (proposed) */
  errorBorder: '#EBC1BE',
  /** White on forest */
  onForest: '#FFFFFF',
  /** Muted text on forest, same as `control` */
  onForestMuted: '#C6CCC9',
} as const;

/* -------------------------------------------------------------------
 * Typography
 *
 * Load with @expo-google-fonts/saira-condensed, /ibm-plex-sans,
 * /ibm-plex-mono in the root layout. Until those packages are added
 * and useFonts() has resolved, these fontFamily strings will fall
 * back silently to the system default on both platforms — safe to
 * reference ahead of install.
 * ---------------------------------------------------------------- */

export const fonts = {
  /** H1, H2, buttons — always uppercase */
  display: 'SairaCondensed_700Bold',
  displaySemi: 'SairaCondensed_600SemiBold',
  /** Body copy */
  body: 'IBMPlexSans_400Regular',
  bodyMedium: 'IBMPlexSans_500Medium',
  bodySemi: 'IBMPlexSans_600SemiBold',
  /** Eyebrows, dates, counts, meta */
  mono: 'IBMPlexMono_400Regular',
  monoSemi: 'IBMPlexMono_600SemiBold',
} as const;

/* -------------------------------------------------------------------
 * Shape and spacing
 * ---------------------------------------------------------------- */

export const radius = {
  /** Match cards, capability cards */
  card: 14,
  /** Primary + ghost buttons */
  button: 10,
  /** Text inputs */
  input: 10,
  /** Filter chips, plan pill */
  chip: 999,
} as const;

export const size = {
  /** Minimum tappable region on any axis — accessibility floor */
  touchMin: 44,
  /** Chip height, matches touchMin */
  chip: 44,
  /** Primary + ghost button height */
  button: 48,
  /** Text input height */
  input: 52,
  /** Tab bar content height (plus safe-area inset) */
  tabBar: 49,
} as const;

export const space = {
  /** Horizontal screen gutter */
  screenX: 20,
  /** Gap between major sections on a screen */
  section: 26,
  /** Padding inside a card */
  cardPad: 16,
  /** Vertical gap between list rows */
  list: 10,
} as const;
