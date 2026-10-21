# Shooting Sports build pack

Hand-off for building the shooter mobile app and the website account area in Cursor.

## What's in here

| Path | What it is |
|---|---|
| `BUILD_SPEC.md` | The brief: product model, design system, every screen and state, API contract, build order, definition of done, open questions |
| `mockups/shootingsports-app-mockups.html` | Interactive visual reference. Open it in a browser |
| `design/tokens.json` | Design source of truth (colours from the live site, type, sizes) |
| `design/theme.ts` | Mobile theme for `apps/mobile` |
| `design/tokens.css` | Website CSS variables to replace the stale set in `resources/css/app.css` |
| `.cursor/rules/shootingsports.mdc` | Project rule so Cursor always follows the spec |

## How to use it

1. Copy this folder's contents into the repo root. Merge `.cursor/` if one exists. Moving `BUILD_SPEC.md` and the other files into `docs/` is fine, as long as you update the paths in the rule file.
2. Start in Cursor's agent mode with: "Read @BUILD_SPEC.md and @design/tokens.json. Do phase 1 from section 6, then stop and show me the diff."
3. Work one phase at a time. For a screen, reference its state codes, for example "Build M3 Calendar, states M3a to M3e, per @BUILD_SPEC.md".
4. Answer the open questions in section 8 as they come up, and edit the spec so the answers persist.
