# Shooting Sports: mobile app and website build spec

Build brief for the shooter-facing mobile app (`apps/mobile/`) and the matching website account area (`/me` routes and the `/desk` home) of shootingsports.co.za. It is written for an AI coding agent working inside the existing repo.

The visual reference is `mockups/shootingsports-app-mockups.html`. Open it in a browser. It has three tabs:

- **Mobile app:** every screen and state, with a note on why each state behaves the way it does.
- **Website:** the five account routes.
- **Review:** decisions, open questions, build order and definition of done.

Where this spec and the mockup disagree, this spec wins.

Design values live in `design/`:

- `tokens.json` is the source of truth.
- `theme.ts` is the mobile theme.
- `tokens.css` holds the website variables.

---

## 0. Ground rules for the agent

1. **Read the repo before writing code.**
   - The site already runs Laravel, Livewire and Filament, with two panels: `/desk` for match directors and other non-staff roles, and `/admin` for staff. It uses Vite and Pest.
   - Extend what exists. Do not scaffold a parallel app.
2. **Versions:**
   - Target the latest stable Laravel, PHP and Filament, and the current major of key packages. Never pin to an older major to make code fit.
   - If the repo is behind, flag it and keep the upgrade as a separate change.
   - The same applies to Expo SDK, Expo Router and React Native.
3. **API shapes:**
   - Endpoints named in this spec exist or are planned. Response shapes marked **proposed** are not confirmed.
   - Read the existing controllers, resources and `App\Support\DeskCapabilities` first, then adapt the client to what is there. Do not change a working API to match this document without asking.
4. **No invented content:**
   - No invented data anywhere: no sample club names, ranges, people, counts, prices or distances in seeders, fixtures, stories or screenshots.
   - Use factories with obviously fake values in tests only.
5. **Future concepts:**
   - Anything marked **future** ships behind a feature flag that defaults to off.
   - Never fake a future feature with a stubbed or hard-coded list.
6. **Vocabulary:**
   - "Sign in" is the verb for the way in, on every surface. "Log out" is the exit.
   - Use "match" in UI copy for now. The planned rename of matches to events is a separate task.
7. **Quality floor:**
   - Text contrast of at least 4.5:1, touch targets of at least 44pt, visible focus, and screen-reader labels on every icon-only control.
   - Every request has a loading state and a recovery path.
8. **Tests:**
   - Pest feature tests for every API endpoint and website route touched.
   - Component tests on mobile for the state logic of each screen (loading, empty, error, populated).

---

## 1. Product model

Every account is a **Shooter**, on Free, Trial or Pro. Four optional **capabilities** stack on top: **Club**, **Shop**, **Range** and **Media**. A person can hold none, one, or all four.

- One capability payload drives both apps: `App\Support\DeskCapabilities`. Mobile and web differ only in layout, never in logic.
- The mobile app always has four tabs: Home, Calendar, Find and Me. Tabs never appear or disappear based on role.
- Management lives in a **Manage** section inside the Me tab and on `/me`. It only renders when the shooter holds at least one capability.
- Capability order is fixed everywhere: **Club, Shop, Range, Media**.
- Platform staff use `/admin`. The app is not an admin tool.
- Registration happens on the website only. The app signs in existing accounts.

---

## 2. Design system

### 2.1 Palette

Taken from the computed styles of the live site. The framework doc's old token table (`--base #0B0D0E`, `--brass #6B7D3A`, mobile gold `#d9ae52`) is stale. Those variables still exist in `resources/css/app.css` but are overridden. Replace them with `design/tokens.css`. The mobile app uses the same palette through `design/theme.ts`.

| Token | Hex | Use |
|---|---|---|
| forest | `#0E3328` | Header and hero band, tab bar, upsell card, active chip, map pins |
| lime | `#C5E07A` | Primary button fill, active tab, eyebrow and pill on forest. Never text on a light background |
| ink | `#10251F` | Text on light backgrounds, text on lime |
| page | `#F7F8F5` | Screen background |
| card | `#FFFFFF` | Cards, lists, chip strip, inputs |
| rule | `#DCE3DE` | Card borders, dividers |
| muted | `#52655E` | Meta and supporting text |
| link | `#245D3F` | Links, ghost buttons, section underline, eyebrow on light |
| tint | `#E7EFE8` | Date blocks, icon tiles, map backdrop |
| error | `#B3261E` on `#FBEAEA` | Errors. Proposed: the site defines no error colour |

### 2.2 Type

- **Saira Condensed** (600, 700): H1, H2, buttons. Always uppercase.
- **IBM Plex Sans** (400, 500, 600): body and titles.
- **IBM Plex Mono** (400, 600): eyebrows, dates, counts, meta.

On mobile, load the fonts with the `@expo-google-fonts` packages. The scale is in `tokens.json`.

### 2.3 Shared components

Build these once per platform, before any screen:

| Component | Spec |
|---|---|
| HeroBand | Forest background. Eyebrow in lime mono uppercase, H1 in white Saira 36 (web 56), optional plan pill. On mobile it extends under the status bar; content starts below the top safe-area inset |
| PlanPill | Bordered pill, mono 12 uppercase. Shows "Free", "Pro" or "Trial · N days left". On forest: lime border and text, or lime fill with forest text for Pro |
| SectionTitle | Saira 20 uppercase, 1px link-green rule underneath |
| MatchCard | White card, 1px rule border, radius 14. Title (Plex 600 16), meta line `date · range, province`, host line. The whole card is one tap target |
| MatchRow | Calendar list row: 52×56 tint date block (day in Saira, month in mono link-green), then title, `range · province`, and `host · discipline` |
| CapabilityCard | White card. Eyebrow (kind), title (entity name), one-line summary, up to 3 items (label left, mono meta right, rule between), primary button. Item content is an **open question**, see section 8 |
| FilterChip | 44pt high pill. Rest: white with chipBorder. Active: forest fill with page-coloured text. Uses `aria-pressed` / `accessibilityState.selected` |
| PrimaryButton | Lime fill, ink text, Saira 16 uppercase, 48pt minimum, radius 10 |
| GhostButton | Transparent, link-green border and text |
| ErrorRow | errorBg background, errorBorder border, error text, alert icon. `role="alert"` / `accessibilityLiveRegion="polite"` |
| EmptyState | Centred stroke icon in the control colour, muted 15px copy, optional single action |
| Spinner | 36pt ring, control track, link-green head. Has a label for screen readers |

---

## 3. Mobile app (`apps/mobile/`)

Expo with Expo Router and TypeScript, as already scaffolded. Store the auth token in `expo-secure-store`. Use `react-native-safe-area-context` for every inset.

### 3.1 Navigation and iPhone rules

- **Tabs:**
  - `app/(tabs)/` has four tabs: `index` (Home), `calendar`, `find` and `me`.
  - The tab bar is forest. Inactive items are control-coloured, the active item is lime, and each tab has an icon over its label.
  - The bar is 49pt of content plus the bottom safe-area inset. Never draw tabs over the home indicator.
- **Top inset:**
  - The HeroBand fills the area under the status bar and Dynamic Island. Content begins at `insets.top`.
  - Do not draw a fake status bar.
- **Tab roots never show a back button.**
- **Pushed screens always show one:**
  - iPhones have no system back button, so every pushed screen needs one. Put each tab in a Stack inside the Tabs layout, so pushes keep the tab bar visible and the correct tab active.
  - The back button is a chevron plus the parent's title ("‹ Home", "‹ Calendar", "‹ Find"), in lime on forest, with at least a 44pt hit area.
  - The edge swipe gesture stays enabled.
- **Modals:**
  - Sign in (`app/login.tsx`) presents as a modal with a close button (X) at top right.
  - On success, dismiss and return to the tab the user came from (default Me).
- **External web pages:**
  - Forgot password, registration and "Open match page" use an in-app browser (`expo-web-browser`, `openBrowserAsync`), which shows Done. Never bounce to Safari.
- **Find to Calendar deep link:**
  - This switches tabs and passes `province` as a param. It shows no back button.
  - The removable province chip in Calendar is the way out.

Route map:

```
app/
  _layout.tsx                 root Stack: (tabs) + login (presentation: 'modal')
  login.tsx                   M1
  (tabs)/
    _layout.tsx               Tabs
    index/                    Stack: index.tsx (M2) → match/[id].tsx (R1)
    calendar/                 Stack: index.tsx (M3) → match/[id].tsx (R2)
    find/                     Stack: index.tsx (M4) → sport/[slug].tsx (R3, future)
    me/                       Stack: index.tsx (M5)
```

Use one shared `MatchDetail` screen component, mounted in both the Home and Calendar stacks, so that Back returns to the correct parent.

### 3.2 Screens and states

Each state below is drawn in the mockup under the same code. Build every one.

#### M1 Sign in (`app/login.tsx`)

`POST /api/v1/auth/login` with email and password. Store the token in SecureStore.

| Code | State | Behaviour |
|---|---|---|
| M1a | Resting | Empty labelled inputs. The Sign in button is enabled once both fields are non-empty |
| M1b | Submitting | Inputs and button disabled. The button shows a small spinner and reads "Signing in". Progress is announced |
| M1c | Invalid credentials | ErrorRow above the fields: "That email and password don't match. Check them and try again." Email is kept, password is cleared and focused, password border uses the error colour. The row stays until the next submit |
| M1d | Offline / network | ErrorRow: "Can't reach the server. Check your connection, then tap Sign in to try again." Both fields are kept |

Also on the screen:

- Copy: "Signed-in shooters save matches, follow sports, and manage their clubs or business."
- A "Forgot password?" link opens the website reset page in the in-app browser (R4).
- A footer reads "New here? Register on the website" and opens registration in the in-app browser.

#### M2 Home (`(tabs)/index`)

`GET /api/v1/events` with default filters and a limit of 6. Pull to refresh.

| Code | State | Behaviour |
|---|---|---|
| M2a | Loading | HeroBand ("National calendar" / "Upcoming matches") plus a centred spinner |
| M2b | Empty | "No upcoming matches yet. Pull to refresh." |
| M2c | Error | "Couldn't load matches. Pull to refresh, or open the full calendar." The "Browse the full calendar" button stays visible |
| M2d | Populated | Six MatchCards, then a ghost "Browse the full calendar" button that switches to the Calendar tab |
| M2e | **Future** personalised | Eyebrow "For you", H1 "What's on", tags for followed sports and places, a "From what you follow" section and an "Also nationally" section. Behind a flag. Needs the follows and saved-search endpoints |

Copy stays generic and national in the MVP, so users don't infer personalisation that isn't there. Tapping a card pushes R1.

#### M3 Calendar (`(tabs)/calendar`)

`GET /api/v1/events` with the family filter (All, Rifle, Handgun, Shotgun), the Novice toggle and optional `province`.

| Code | State | Behaviour |
|---|---|---|
| M3a | Loading | Filter strip stays usable, spinner below |
| M3b | Default | Sticky chip strip under the HeroBand. Family chips scroll horizontally; the Novice toggle is pinned to the right with a divider so it never scrolls away. MatchRows are grouped under month headers |
| M3c | Province filter | Arrived from the Find map. A second row reads "Province" with a removable chip (for example "Gauteng ×"). Removing it reloads the list without the province filter |
| M3d | Empty after filter | "No matches match your filters." plus a ghost "Clear filters" button that resets family, Novice and province |
| M3e | Error | "Couldn't load the calendar. Pull to refresh." The strip stays visible |

Filters are single-select for family, with a boolean for Novice. Changing a filter refetches. Tapping a row pushes R2. Do not add date, distance or text-search filters until the API supports them. Text search is listed as not built.

#### M4 Find (`(tabs)/find`)

The mode chips are Events (default), Clubs, Ranges, Shops and Sports. They scroll, and the active chip is always scrolled into view.

| Code | State | Behaviour |
|---|---|---|
| M4a | Events · clusters | Map of South Africa with one pin per province showing the upcoming-match count. Footer strip: "N upcoming matches at M pinned ranges". Counts come from the events data; see the API note below |
| M4b | Events · pin tapped | Other pins dim. A callout shows the province name, "N upcoming · M ranges" and an "Open in calendar" button, which switches to Calendar with `province` set (M3c) |
| M4c | Events · loading | Map dimmed, spinner overlay, footer reads "Loading upcoming matches" |
| M4d | Events · error | Map dimmed, "Couldn't load matches for the map. Pull to retry." Chips and tab bar stay |
| M4e, g, i, k | Clubs, Ranges, Shops, Sports · today | Centred icon plus "{Mode} search opens here when its API endpoint lands." Never a zero-results message |
| M4f | **Future** Clubs list | Province and Discipline dropdown chips. Rows: club name, town and province, disciplines |
| M4h | **Future** Ranges list | Same layout as Clubs, with distances and disciplines on the third line |
| M4j | **Future** Shops tiles | Province chip, then a 2-column grid of square logo tiles with name and town |
| M4l | **Future** Sports tiles | Grouped under Rifle, Handgun and Shotgun headers, 2-column tiles. A tile pushes R3 |

The map is a way into Calendar, not a second list. Use a map library that supports custom markers. The mockup outline is a sketch, not the production map.

#### M5 Me (`(tabs)/me`)

Calls `GET /api/v1/me` (profile, plan, capabilities summary), `GET /api/v1/me/capabilities` (card payload) and `GET /api/v1/me/saved-events`.

Section order: HeroBand (eyebrow "My home", name as H1, PlanPill), then Manage (only if capabilities exist), then Saved matches, then the Pro upsell (only on Free), then a Log out row.

| Code | State | Behaviour |
|---|---|---|
| M5a | Signed out | Centred icon, "Sign in to see your home, save matches, and manage your clubs or business." and a "Sign in" button that opens M1. The tab bar stays |
| M5b | Free, no capabilities | Saved matches empty state: "Nothing saved yet. Open the calendar and save a match." Then the upsell, then Log out |
| M5c | Free, one capability | Manage section with one CapabilityCard. Works for Club, Shop, Range or Media |
| M5d | Pro, no capabilities | Pill reads "Pro" (filled). No upsell. Saved list populated, with a filled bookmark button labelled "Remove from saved" on each row |
| M5e | Trial, no capabilities | Pill reads "Trial · N days left". No upsell |
| M5f | Free, all four | Four cards in fixed order: Club, Shop, Range, Media. Then saved matches, upsell and Log out |
| M5g | Loading | Full-screen spinner, tab bar visible |
| M5h | Error on `/me` | Error bar at the top: "Couldn't load your home." / "Pull to retry." The skeleton below shows at 30% opacity and does not jump on retry |

- The upsell card is forest with eyebrow "Pro", title "Go Pro", copy "Pro lifts the Free plan limits." plus the price, and an "Upgrade to Pro" button. Price and limits are open questions; do not hard-code them.
- Log out clears SecureStore and returns to M5a.
- A capability card's action ("Open club desk" and the others) opens the matching `/desk` page in the in-app browser. The exact target is an open question.

#### R Pushed routes

| Code | Route | Behaviour |
|---|---|---|
| R1 | `index/match/[id]` | Back button "‹ Home" and a share button (native share sheet). HeroBand with discipline eyebrow, match title, `date · range, province`. A details list of Date, Range, Province, Host and Discipline. Primary "Save match" and ghost "Open match page" (website match page in the in-app browser) |
| R2 | `calendar/match/[id]` | Same screen with "‹ Calendar". Saved state shows a ghost "Saved" button with a filled bookmark and `aria-pressed` |
| R3 | `find/sport/[slug]` **future** | Back button "‹ Find". Family eyebrow, sport title, Articles cards (title, source, date), Magazines 2-column covers |
| R4 | In-app browser | Forgot password: the website reset page in an `expo-web-browser` sheet with Done |

Saving while signed out opens M1, then completes the save after sign-in.

### 3.3 Data and state

- Use one API client with a base URL from config, the bearer token from SecureStore, and a 401 handler that clears the token and shows M5a.
- Use a query cache (TanStack Query or equivalent). Pull to refresh invalidates the query.
- Treat network failure and HTTP errors differently in copy (M1c vs M1d).
- No offline cache beyond what the query library keeps in memory.

---

## 4. Website (Laravel, Blade, Livewire, Filament)

The account area uses the same capability payload and vocabulary as the app. The layout follows the live site:

1. Forest top nav (wordmark, Events, Sports, Find, Industry, account name and plan pill)
2. Forest hero
3. White sub-navigation: My home, Calendar, Log, Settings, and Manage only with capabilities
4. Content on the page background inside a 12-column grid

The existing hub is `resources/views/public/me/index.blade.php`. The desk home is `resources/views/filament/desk/pages/desk-dashboard.blade.php`.

| Route | Layout | Notes |
|---|---|---|
| `/me` | Manage takes 8 columns (2-up CapabilityCards); saved matches take 4 columns, with the upsell on Free | Mirrors M5. With no capabilities, saved matches go full width |
| `/me/calendar` | Chips (family, Novice, removable province) above a table: Date, Match, Range, Province, Discipline, Host, save toggle | Livewire filters. The existing calendar filter component may be reusable |
| `/me/log` | Saved-match trail grouped by month (8 columns), plus a note card in the right 4 columns | Richer activity history (results, attendance) is **future** and is not shown as an empty list |
| `/me/settings` | Account form (name, email, change password) in 7 columns; plan card plus Sign out in 5 columns | The app deep-links here for anything needing a form |
| `/desk` | Four CapabilityCards in a row, in Club, Shop, Range, Media order | `/desk` users must never reach `/admin`. Check panel access policies per capability |

Every route needs loading, empty and error handling to the same standard as mobile. Every Livewire action shows a pending state. Below about 740px the grids stack to one column.

---

## 5. API contract

Existing or named in the framework. Verify the shapes in the code.

| Method | Path | Use |
|---|---|---|
| POST | `/api/v1/auth/login` | M1. Returns a token (verify the format: Sanctum personal access token assumed) |
| GET | `/api/v1/events` | M2 (limit 6), M3 (filters), M4 counts |
| GET | `/api/v1/me` | M5 profile, plan, capabilities summary |
| GET | `/api/v1/me/capabilities` | Full CapabilityCard payload, shared with `/desk` home |
| GET | `/api/v1/me/saved-events` | Saved list on M5 and `/me/log` |

**Needed and not listed in the framework.** Confirm they exist before building against them; if not, add them with tests:

| Method | Path | Why |
|---|---|---|
| GET | `/api/v1/events/{id}` | R1, R2 match detail |
| POST / DELETE | `/api/v1/me/saved-events/{event}` | Save and unsave from R1, R2, M5d and the web calendar |
| POST | `/api/v1/auth/logout` | Revoke the token on Log out |
| GET | `/api/v1/events/summary?group=province` (proposed) | M4 pin counts without paging the whole events list. Alternatively compute client-side if the list is small, but measure first |

**Proposed** event fields the UI needs: `id`, `title`, `starts_on`, `ends_on`, `range.name`, `province`, `host.name`, `discipline.name`, `family` (rifle, handgun or shotgun), `novice_friendly` (bool), `url` (website match page). Map them from what the existing resource returns.

**Proposed** capability card: `kind` (club, shop, range or media), `title`, `summary`, `items[]` (`label`, `meta`, max 3), `action` (`label`, `url`). Plan: `plan` (free, trial or pro), `trial_ends_at`.

---

## 6. Build order

| Phase | Ship | Done when |
|---|---|---|
| 1. Foundation | Tokens on both platforms; replace the stale CSS variables; shared components from section 2.3 | App and website render the same labels and hierarchy from one token set |
| 2. API surfaces | M1, M2, M3, M5 and R1, R2 with every state | Every request has a visible loading state and a recovery path |
| 3. Role stacking | Manage cards from the capability payload; `/me`, `/me/settings`, `/me/log`, `/desk` home | Shooters with no capabilities and with four are equally easy to read; `/desk` cannot reach `/admin` |
| 4. Discovery | M4 Events map and pin to Calendar deep link; directory placeholders | No placeholder looks like a zero-results search |
| 5. Increments (flagged) | M2e, M4f/h/j/l, R3, activity history | Each lands with its own real endpoint |

---

## 7. Definition of done

- Every state in section 3.2 and every route in section 4 is implemented and reachable in a test or story.
- Every empty state says whether there is no data or the feature isn't available yet.
- App and website show the same plan and capability logic from the same payload.
- A four-capability shooter can reach saved matches and Log out without hunting.
- iPhone: no content under the Dynamic Island or home indicator; every pushed screen has a labelled back button and edge swipe; external pages open in-app with Done.
- Contrast, 44pt targets, focus states and screen-reader labels are checked on iOS VoiceOver and in a browser.
- Pest and mobile tests pass. No real or invented business names in seed data.

---

## 8. Open questions (ask before building these parts)

1. **Capability card contents.** What each card's up-to-3 items show for Club, Shop, Range and Media. The framework doc was cut off before this spec. Build the card generically from the payload until it's confirmed.
2. **Pro.** Price, which Free limits Pro lifts, and the upsell copy.
3. **Trial end.** What the pill and Me tab show on the last day and after expiry.
4. **Me order for stacked roles.** The framework puts Manage above saved matches. With three or more capabilities, should Manage collapse to a compact strip so saved matches stay near the top?
5. **Desk hand-off.** Whether "Open club desk" opens `/desk` generally or a capability-scoped page, and how the app session carries into the browser. A signed one-time link is suggested; confirm.
6. **Error colour.** Confirm `#B3261E` or supply the site's own.
7. **Map provider** for M4, and whether pins sit at province centroids or are clustered from range coordinates.
