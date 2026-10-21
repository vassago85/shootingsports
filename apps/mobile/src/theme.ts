/**
 * Mobile palette — aligned to the web's `resources/css/app.css` tokens
 * so the native app and shootingsports.co.za feel like the same brand
 * when a shooter flips between them. The web is canonical; mobile
 * mirrors it.
 *
 * The mockup framework (`docs/superpowers/specs/2026-10-20-app-and-web-
 * mockup-framework.md`) flagged this alignment as a decision to make
 * once the first mockups came back. Paul saw them side-by-side and
 * picked "mobile matches web" — this file is that decision captured.
 *
 * Keep these in sync with the CSS custom-properties in app.css. If a
 * token changes there, change it here too.
 */
export const colors = {
  /** Page background — `--base` on web */
  base: '#0B0D0E',
  /** Card / section background — `--surface` on web */
  surface: '#15191B',
  /** Elevated card, filter-chip rest — `--surface-2` on web */
  surface2: '#252B2E',
  /** Primary text, headings — `--ink` on web */
  ink: '#F1F2EE',
  /** Button background, active chip — `--brass` on web (olive-green) */
  brass: '#6B7D3A',
  /** Hover / active text — `--brass-dk` on web */
  brassDark: '#879A4A',
  /** Text that sits on top of a brass button */
  onBrass: '#1a1405',
  /** Muted / supporting text — `--slate` on web */
  slate: '#9CA3A5',
  /** Hairline borders — `--rule` on web */
  rule: '#252B2E',
  /** Error copy — keeps a warm red; no web token to mirror */
  error: '#f28c8c',
  /** Transparent brass tint used by the plan pill when Pro / Trial */
  brassTint: 'rgba(107,125,58,0.14)',
};
