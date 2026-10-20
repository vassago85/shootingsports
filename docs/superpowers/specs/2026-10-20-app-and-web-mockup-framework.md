# App and web mockup framework

A single reference that lists every screen and every state we need
mockups for. Paste the relevant section into Claude or ChatGPT with
one of the prompt scaffolds at the bottom to get back something that
drops straight into the existing scaffold.

This supersedes the shooter-only roadmap for mockup purposes. The
one-app, stacked-roles plan is at
[docs/superpowers/specs/2026-10-20-multi-role-app-scope.plan.md](2026-10-20-multi-role-app-scope.plan.md)
if a mockup brief needs the "why".

- Mobile scaffold lives at [apps/mobile/](../../../apps/mobile/).
- Website hub lives at [resources/views/public/me/index.blade.php](../../../resources/views/public/me/index.blade.php)
  and the desk home at
  [resources/views/filament/desk/pages/desk-dashboard.blade.php](../../../resources/views/filament/desk/pages/desk-dashboard.blade.php).

## Who the apps are for

Every account is a **Shooter** (Free or Pro). Optional **capabilities**
stack on top: Club, Shop, Range, Media. A person can hold zero, one,
or all four. The mobile app and the website use the exact same model
and the same capability payload (`App\Support\DeskCapabilities`), so
mockups only differ in layout — never in logic.

| Audience | Mobile tab | Website route |
|---|---|---|
| Guest / Shooter Free | all four tabs | `/me`, `/me/calendar`, `/me/log`, `/me/settings` |
| Shooter Pro | same | same, limits lifted |
| Shooter + Club admin | all four, Manage hub on Me | `/me` with Manage block, `/desk` |
| Shooter + Shop owner | same | same |
| Shooter + Range owner | same | same |
| Shooter + Media partner | same | same |
| Platform staff | app not used for admin | `/admin` |

## Design tokens

Mockups must respect these — the scaffold hard-codes them.

### Website (`resources/css/app.css`)

| Token | Value | Role |
|---|---|---|
| `--base` | `#0B0D0E` | Page background |
| `--surface` | `#15191B` | Card / section background |
| `--surface-2` | `#252B2E` | Elevated card, filter chip rest |
| `--ink` | `#F1F2EE` | Primary text, headings |
| `--brass` | `#6B7D3A` | Primary action, active chip |
| `--brass-dk` | `#879A4A` | Hover / active text |
| `--brass-lt` | `#879A4A` | Link underline / secondary accent |
| `--slate` | `#9CA3A5` | Muted / supporting text |
| `--rule` | `#252B2E` | Hairline borders |
| `--f-display` | `Saira Condensed` | H1–H4, buttons, `.label` eyebrows |
| `--f-body` | `IBM Plex Sans` | Body copy |
| `--f-mono` | `IBM Plex Mono` | Metadata (dates, counts, codes) |

Button convention: brass background, `#1a1405` text, uppercase 15px
Saira Condensed, 11px vertical / 22px horizontal padding, 1px brass
border. `.btn.ghost` flips to transparent background with brass
border and brass text.

### Mobile app (`apps/mobile/`)

| Token | Value | Role |
|---|---|---|
| Page background | `#101516` | Full-screen bg |
| Card background | `#1c2426` | List rows, cards |
| Filter bar bg | `#161c1e` | Chip strip under header |
| Primary text | `#f4f0e6` | Headings, body |
| Muted text | `#a3adaa` | Dates, meta, descriptions |
| Accent | `#d9ae52` | CTA, active chip, eyebrow |
| On-accent text | `#232a2c` | Text sitting on accent |
| Error | `#f28c8c` | Error copy |
| Border | `#3a4548` | Chip rest, pill border |

Mockups should **align the two palettes over time** (web `--brass` is
olive-green `#6B7D3A`; mobile accent is gold `#d9ae52`). For now,
keep each surface true to its own palette so mockups match the live
pixels. Pick a direction when the first round of mockups comes back.

## Shared components

| Component | Where | Shape |
|---|---|---|
| Hero band | Top of every page | eyebrow (uppercase, mono-ish) + H1 + optional plan tag |
| Capability card | `/me`, desk home, mobile Me tab | eyebrow (kind), title, summary, up to 3 items, primary action button |
| Match card | Home + Calendar on both apps | title, date, location, host; tap → match detail |
| Filter chip row | Calendar, Find | pill chips in a horizontal strip; active chip flipped colours |
| Plan pill | `/me`, mobile Me tab | small bordered pill with "Free", "Pro", or "Trial · N days left" |
| Section title | Everywhere | H2, left-aligned, underlined by a brass hairline |
| Muted paragraph | Under titles | `--slate` on web, `#a3adaa` on mobile |
| Primary action button | Everywhere | brass background, uppercase display font |
| Ghost button | Secondary actions | transparent background, brass border + text |
| List item | Calendar, saved matches, Find results | 1-line title, 1-line meta, optional 1-line sub-meta |
| Province cluster pin | Find (Events mode) | pin with count badge; tap opens callout → Calendar for that province |

## Mobile app screens

The mobile app has **four tabs, always**, plus a small set of
secondary routes. No tab appears or disappears based on role — the
Manage hub lives inside the Me tab and only renders when the shooter
also wears a club, shop, range, or media hat.

### M1. Login (`app/login.tsx`)

- **Purpose:** email + password sign-in against `POST /api/v1/auth/login`. No social, no SSO. No registration — guests register on the website.
- **Data:** email, password, remember-me-ish via SecureStore.
- **States:**
  - Resting (empty inputs)
  - Submitting (button disabled, spinner)
  - Error (invalid credentials, red error row above the form)
  - Offline (network error, same error row with retry copy)
- **Interactions:** submit → on success, pop back to the tab the user came from (default Me). "Forgot password?" link opens the website.
- **Copy direction:** "Sign in" (not "Log in" — the Me tab also uses "Log in"; pick one and standardise). "Signed-in shooters save matches, follow sports, and manage their clubs or business."

### M2. Home — tab 1 (`app/(tabs)/index.tsx`)

- **Purpose:** the reason to open the app on a Friday. Next six upcoming matches on the national calendar.
- **Data:** `GET /api/v1/events` (default filters, limit 6).
- **States:**
  - Loading (centered spinner)
  - Empty (`"No upcoming matches yet. Pull to refresh."`)
  - Error (red copy; still show the "Browse the full calendar" CTA)
  - Populated (hero + match cards + CTA)
- **Interactions:** tap a card → match detail. Tap CTA → Calendar tab. Pull to refresh.
- **Future state (not yet built):** personalised "what's on" for sports and places you follow once follows + saved-searches API endpoints exist. Mockup should show both the generic MVP state and a personalised future state.

### M3. Calendar list — tab 2 (`app/(tabs)/calendar.tsx`)

- **Purpose:** information-dense repeat-use surface. Filterable list of upcoming matches.
- **Data:** `GET /api/v1/events` with filters.
- **States:**
  - Loading
  - Populated with default filters
  - Populated with province filter (deep-linked from the map pin)
  - Empty-after-filter (`"No matches match your filters."`)
  - Error
- **Filter chips:** All / Rifle / Handgun / Shotgun (family), plus Novice toggle. Chips sit in a sticky strip under the tab header.
- **Interactions:** tap chip → re-filter. Tap row → match detail. Pull to refresh.

### M4. Find — tab 3 (`app/(tabs)/find.tsx`)

- **Purpose:** one discovery surface for Events, Clubs, Ranges, Shops, Sports. The chips switch mode; the surface below adapts.
- **Chips:** Events (default) · Clubs · Ranges · Shops · Sports.
- **States:**
  - Events chip: province cluster map, pins with upcoming-match counts, footer strip "N upcoming matches at M pinned ranges"
  - Events chip loading / error
  - Clubs chip: directory list placeholder today (`"Clubs search opens here when its API endpoint lands."`); future: list + province + discipline chips
  - Ranges chip: same placeholder → list
  - Shops chip: same placeholder → tiled list of shop logos
  - Sports chip: same placeholder → sport tiles grouped by family, each opens a sport landing with articles + magazines
- **Interactions:** Events map pin tap → opens Calendar tab pre-filtered to that province. Future chips tap → directory lists.
- **Mockup needs both states per non-Events chip:** the placeholder today, and the future populated list.

### M5. Me — tab 4 (`app/(tabs)/me.tsx`)

Longest, most-stated screen in the app. Mockup needs **every** state below.

- **Purpose:** shooter's home inside the app. Plan state, saved matches, Pro upsell, logout, and (for role-stacked users) the Manage hub.
- **Data:** `GET /api/v1/me` (profile + capabilities + plan), `GET /api/v1/me/capabilities` (full card payload), `GET /api/v1/me/saved-events`.

#### States

- **M5a. Signed out:** full-screen "Sign in to see your home, save matches, and manage your clubs or business." + brass "Log in" button.
- **M5b. Signed in · Shooter Free · no capabilities:**
  - Hero: "My home" eyebrow, name as H1, "Free" plan pill
  - Saved matches section (empty state: `"Nothing saved yet. Open the calendar and save a match."`)
  - Pro upsell card at the bottom
  - "Log out" row at the very bottom
- **M5c. Signed in · Shooter Free · 1 capability (any of Club / Shop / Range / Media):**
  - Hero as M5b
  - **Manage section** with 1 capability card (see card specs below)
  - Saved matches
  - Pro upsell
  - Log out
- **M5d. Signed in · Shooter Pro · no capabilities:**
  - Hero plan pill reads "Pro" (no upsell card)
  - Saved matches
  - Log out
- **M5e. Signed in · Shooter Trial · no capabilities:**
  - Hero plan pill reads "Trial · N days left"
  - Saved matches
  - No upsell card (user is already on Pro features)
  - Log out
- **M5f. Signed in · Shooter + all four capabilities:**
  - Hero
  - Manage section with four cards in a vertical stack (Club, Shop, Range, Media in that order)
  - Saved matches
  - Pro upsell (if Free)
  - Log out
- **M5g. Loading:** full-screen spinner.
- **M5h. Error on `/me`:** red copy at the top, rest of the layout greyed out, "Pull to retry" copy.

#### Capability cards (the Manage hub)

Every card has the same shape so the layout stays symmetrical when more than one is shown. Each card is a single pressable region with:

- **Eyebrow (uppercase mono):** `CLUB` / `SHOP` / `RANGE` / `MEDIA`
- **Title:** e.g. "Clubs you manage"
- **Summary line:** one plain-English sentence (e.g. "2 clubs · 3 events this week")
- **Up to 3 items:** names of the clubs / shops / ranges / magazine drafts
- **Primary action button:** `"Open club desk ↗"` / `"Open shop desk ↗"` / `"Open range desk ↗"` / `"Open magazine desk ↗"` — opens the website in the system browser via `Linking.openURL`

Card-specific copy:

| Kind | Title | Summary pattern | Primary action |
|---|---|---|---|
| Club | "Clubs you manage" | "N clubs · M events this week · K enquiries waiting" | "Open club desk ↗" |
| Shop | "Shop listings" | "N listings · M enquiries · K advert placements active" | "Open shop desk ↗" |
| Range | "Ranges you manage" | "N ranges · M events here this week" | "Open range desk ↗" |
| Media | "Magazine and articles" | "N drafts · latest issue: {date}" | "Open magazine desk ↗" |

Light in-app actions planned for later (set event status, reply to enquiries). Mockups should **leave room** for a secondary action row below the primary button but ship v1 with the primary button only.

### M6. Match detail (`app/matches/[slug].tsx`)

- **Purpose:** everything about one match. Entry and payment open the system browser; the app never handles money.
- **Data:** `GET /api/v1/events/{slug}`.
- **States:**
  - Loading
  - Populated with upcoming match (date in the future)
  - Populated with "entries open" status (prominent entry CTA)
  - Populated with "full" status (CTA disabled, waitlist copy)
  - Populated with "postponed" / "cancelled" (status band at the top, muted)
  - Populated with "completed" (results CTA replaces entry CTA when `results_url` is set)
  - Not-found (should rarely happen; fall back to Calendar)
- **Hero:** eyebrow with sport family, title as H1, date + venue + host.
- **Body sections:** description, round / target / stage counts, venues map snippet, novice-friendly badge if applicable.
- **Action bar (sticky bottom on tall content):** Save, Add to device calendar, Share, Open entry (website), Directions.
- **Guest version:** Save and Add-to-calendar actions become "Log in to save" chips that bounce to M1.

### M7. Calendar sub-states already covered under M3

(Explicit so Claude / GPT doesn't invent a separate "my calendar" tab — the single Calendar tab serves both all-matches and province-filtered views.)

## Website screens

The website header stays shooter-simple: Calendar, Find, Me. Account
menu shows Manage only when the user has a capability, and Admin only
for staff.

### W1. Public home (`/`)

- Already shipping. Mockup not needed as part of this round unless we rebrand.

### W2. Login (`/login`)

- Already shipping. Mockup not needed unless we rebrand.

### W3. Shooter home — `/me` (`resources/views/public/me/index.blade.php`)

- **Purpose:** mirror of mobile Me (M5). One hero, optional Manage block, Shooting section with four navigation cards, optional Pro upsell.
- **States:**
  - **W3a. Shooter Free, no capabilities:** hero + Shooting cards (My calendar / My log / My memberships / Settings) + Pro upsell
  - **W3b. Shooter Pro, no capabilities:** same without Pro upsell
  - **W3c. Shooter Trial, no capabilities:** hero shows "Trial · N days left" pill, no upsell
  - **W3d. Shooter + 1 capability:** hero + Manage block with 1 card + Shooting cards
  - **W3e. Shooter + all four capabilities:** hero + Manage block with 4 cards in a responsive grid + Shooting cards
- **Shooting cards** (always present when signed in):
  - My calendar → `/me/calendar` — shows `"N matches saved"`
  - My log → `/me/log` — shows `"N/3 logged"` for Free, `"N logged"` for Pro
  - My memberships → `/my-memberships` — "Club memberships, invoices, payments"
  - Settings → `/me/settings` — "Notifications, profile, email"
- **Manage cards** mirror the mobile capability cards (M5 above) in layout but open the respective desk route in the same tab.

### W4. Shooter calendar — `/me/calendar`

- **Purpose:** the dense personal calendar. Saved events, upcoming vs. past, filters.
- **Already shipping** at `ShooterCalendarController@mine`.
- **States:** upcoming (default), past (year filter), empty.
- Mockup should propose the "upcoming vs. past" tab toggle at the top.

### W5. Shooter log — `/me/log`

- **Purpose:** attendance record for dedicated-status renewals and personal history. Add, edit, export, print.
- **Already shipping** at `ShooterLogController@index`.
- **States:**
  - Free user under 3 slots: list + "Add a match" button, status pill shows `"2/3 slots used"`
  - Free user at 3 slots: list + disabled Add button + upgrade nudge "Pro removes the cap"
  - Pro user: unlimited list + Add + Export CSV + Print buttons
  - Empty: `"No matches logged yet."` + Add button
  - Year-filtered: list filtered to one calendar year + year chips

### W6. Shooter settings — `/me/settings`

- **Purpose:** notification prefs today (NotificationPreferences Livewire). Will absorb profile fields (home province, travel radius) in a later slice.
- **States:**
  - All toggles off (base: just marketing + match alerts + weekly digest + product updates)
  - All toggles on
  - Mid-save (optimistic toggle)
  - Error on save (toast + revert)

### W7. Desk home — `/desk` (Filament page `App\Filament\Desk\Pages\DeskDashboard`)

- **Purpose:** the entry point to the heavy editor. The dashboard surfaces the capability cards for anyone with 2+ capabilities, otherwise jumps straight to the single mode.
- **States:**
  - **W7a. No capabilities:** "Nothing to manage here yet" with claim/list/claim-range CTAs (`"Claim a club"`, `"List a shop"`, `"Claim a range"`, "Open your calendar")
  - **W7b. One capability (e.g. club only):** jumps to club mode content, no cards
  - **W7c. Two or more capabilities:** capability cards grid at the top, mode content below (defaults to the first capability)
  - **W7d. Four capabilities:** full grid of four cards, mode content below

### W8. Club desk pages (`/desk/organisations/*`)

The heavy editor the mobile app's Club capability card opens into.

- **Members list** — searchable, filterable by status (active / lapsed / pending / rejected), bulk actions
- **Member detail** — profile, invoices, membership history, notes
- **Events wizard** — multi-step: basics → date & venue → disciplines → entry → publish
- **Invoices** — list + detail + mark-paid flows
- **Billing** — Paystack subaccount setup, payout settings, fee statements
- **Guest days / fees / invites** — settings pages, straightforward CRUD
- Mockup priority: Members list, Member detail, Events wizard step 1. The rest can follow once those three are approved.

### W9. Shop desk pages (`/desk/providers/*`)

- **Listing editor** — name, logo, description, categories, service areas, opening hours, photos
- **Creatives** — advert banners for placement rotations
- **Enquiries inbox** — list with read/unread + read-only reply (mail opens from user)
- **Billing** — subscription state, invoices
- Mockup priority: Listing editor, Enquiries inbox.

### W10. Range desk pages (`/desk/venues/*`) — **new**

Does not exist in Filament yet. Needs to be built out but mockups
should design it alongside the others.

- **Range editor** — name, address, geocode, hours, range officer notes, fees, photos, verification state
- **Verification queue** — pending claims, staff-visible evidence uploads
- **Upcoming events at this range** — read-only list sourced from any event with this venue linked
- Mockup priority: Range editor.

### W11. Media partner desk pages (`/desk/magazines/*`)

- **Issues list** — one row per issue, status (draft / submitted / published)
- **Issue editor** — title, cover, PDF upload, articles list
- **Articles list** — searchable, filterable by status
- **Article editor** — title, body, cover, publish state
- Mockup priority: Issues list, Issue editor.

### W12. Public match detail (`/events/{slug}`)

- Already shipping. Mockup not needed unless rebrand.

### W13. Public calendar (`/calendar`)

- Already shipping. Mockup not needed unless rebrand.

### W14. Public map (`/map`)

- Already shipping. Mockup not needed unless rebrand.

### W15. Upgrade — `/upgrade`

- Already shipping. One screen with Pro features + Paystack button.

### W16. Nav header (desktop)

Three columns: brand, primary nav (Calendar / Find / Sports / Articles / Magazines / About), account menu. Account menu entries:

- **Signed out:** Log in · Sign up
- **Shooter Free:** My home · My calendar · My log · Email preferences · Log out
- **Shooter + any capability:** adds **Open desk**
- **Staff:** adds **Admin**

### W17. Nav header (mobile)

Hamburger-triggered sheet. Same entries as W16, stacked.

## Empty, loading, error patterns

Standardise these across every screen so Claude / GPT doesn't invent variations.

| Pattern | Web look | Mobile look |
|---|---|---|
| Loading | centered spinner, no copy | centered spinner, no copy |
| Empty (first-use) | muted paragraph + brass CTA | muted paragraph + brass CTA |
| Empty (filter removed everything) | muted paragraph, no CTA, "Clear filters" ghost button | same |
| Error (recoverable) | red one-liner above the content, content hidden | red one-liner, no content |
| Error (hard — not found, forbidden) | centered hero with H1 + ghost "Go back" | same |
| Rate-limited / throttled | banner at top: "Hold on — try again in a few seconds" | toast |

## Role-walked user journeys (for mockup scene-setting)

Each journey below is one sentence per screen. Hand this to the
mockup prompt so the designer understands the flow between screens
even when rendering each one in isolation.

- **New shooter signup → first saved match:** M1 → M5a → tap Log in → website register in browser → back to M2 → tap match → M6 → tap Save → M5b with 1 saved
- **Pro trialist:** M5e (trial pill, no upsell) → tap M3 → filter rifle → save three matches → M5e with 3 saved
- **Club admin opens their club:** M5c with Club card → tap "Open club desk ↗" → browser lands on W8 members list
- **Shop + Range stacked:** M5c with 2 cards → tap Range card → browser on W10 → back to app, tap Shop card → browser on W9
- **Media partner uploads an issue:** M5c with Media card → tap "Open magazine desk ↗" → W11 issues list → New issue → W11 issue editor

## Component inventory (name + where used)

Standardise names so mockup files are easy to port into code.

| Name | Mobile StyleSheet key | Web class |
|---|---|---|
| Hero band | — | `.page-hero` + `.wrap` |
| Eyebrow label | `styles.eyebrow` | `.label` |
| H1 | `styles.h1` | `h1` (`--f-display`) |
| Section title | `styles.sectionTitle` | `.section-title` |
| Muted paragraph | `styles.muted` | `.muted` |
| Capability card | `styles.card` + eyebrow + title + items + action | `.me-card` |
| Match card | `styles.card` + title + meta + sub-meta | `.rail-item` / `.dope-title-link` |
| Filter chip | `styles.chip` / `styles.chipActive` | `.filters button` / `[aria-pressed="true"]` |
| Primary button | `styles.cta` / `styles.ctaText` | `.btn` |
| Ghost button | — | `.btn.ghost` |
| Plan pill | `styles.chip` | `.tag` with transparent background + border |
| Error row | `styles.error` | inline red paragraph |
| Logout row | `styles.logoutRow` | — |

## Prompt scaffolds for Claude / ChatGPT

Use one of these as the system / first message, then paste the
section of this doc that matches the screen(s) you want designed.
Add your own stylistic direction after.

### Scaffold A — single screen, high fidelity

```
You are a product designer producing a high-fidelity mockup for a
South African shooting-sports app.

Context: the full screen inventory and design tokens are below.
Produce **one** mockup for the screen I name, in all the states
listed for that screen. Use the exact design tokens listed under
"Design tokens" — do not invent colours, fonts, or spacing.
Deliver as:

1. A labelled wireframe for each state (ASCII or SVG is fine).
2. A short rationale explaining layout choices tied to the states.
3. A list of any copy you'd change, with the reason.

Do not invent features that aren't listed. If a state is unclear,
list the clarifying questions at the top before mocking.

Screen to mock: {paste the M-number or W-number and its full
section from the doc}

Design tokens: {paste the Design tokens section}

Shared components: {paste the Shared components table}

Empty/loading/error patterns: {paste that section}
```

### Scaffold B — full-surface flow (whole tab or whole hub)

```
You are a product designer producing a flow of mockups for one
surface of a South African shooting-sports app.

Produce mockups for every screen in the surface I name, in every
state listed. Return a storyboard: one labelled frame per state,
grouped by screen. Include transition annotations between frames
(what the user tapped / typed to move).

Use the exact design tokens listed. Do not invent features.

Surface to mock: {e.g. "Mobile Me tab", "Website desk pages for
Club"}

Full doc section: {paste the relevant M or W block}

Design tokens: {paste}

Shared components: {paste}
```

### Scaffold C — comparison / audit of an existing screen

```
You are reviewing a shipped screen in a South African shooting-
sports app against its intended design spec.

I will paste:
1. The spec for the screen (states, copy, components).
2. A screenshot or Figma frame of the current implementation.

Return:
1. State coverage: which states in the spec are shown / missing.
2. Token drift: colours / fonts / spacing that don't match the
   listed design tokens.
3. Copy drift: wording that doesn't match the spec.
4. Prioritised list of fixes (P0 = breaks a state, P1 = wrong
   token, P2 = polish).

Spec: {paste the M or W block}
Design tokens: {paste}
Current implementation: {paste image / description}
```

## What is deliberately out of scope for this round

- Admin / `/admin` panel mockups. Staff workflow is not a user-facing surface and keeps the Filament defaults.
- In-app purchase / Pro checkout UI. Pro is sold on the website only, so the mobile app will never render a price or a buy button. Locked rows just link out.
- Email / notification mockups. Those live in the Mailgun templates and are tracked separately.
- Push-notification body copy. Covered by plan step 4 (`docs/superpowers/specs/2026-10-20-multi-role-app-scope.plan.md`).
- Any screen for a role we don't actually have (coach, judge, armourer, event sponsor). Add a capability to the plan first; mockup second.
