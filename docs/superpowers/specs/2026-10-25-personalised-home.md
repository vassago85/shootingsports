# Personalised Home for authed users

> Status: **Spec, not implemented.** Drafted 2026-10-25 after a product
> conversation about Home / Calendar / Find duplication and a
> third-party advisor review of the premium model.

## Problem

Home today shows two slices of upcoming matches ("This weekend",
"Next 30 days") and a button that opens the Calendar. The Calendar
shows the same matches, filterable. For anonymous visitors Home works
as a shop window. For a logged-in regular user it's wasted space:
every match on Home is one tap away in the Calendar, and the user has
no way to say "I only care about Precision Rifle in Gauteng".

## Goal

Home becomes a curated feed for signed-in users built from what the
user follows (clubs, disciplines) plus a home-area setting (province).
Anonymous and signed-out users keep today's generic Home as the shop
window.

Nothing about Calendar changes — it stays the browse-everything view.

## Non-goals

- No new onboarding for anonymous users (shop window stays as-is).
- No `entries_closes_at` / `entries_opens_at` columns in this phase.
  See _Deferred_ below for why.
- No partner-discount or paid early-access flows. Those depend on
  clubs agreeing to it and most entries run through the host's own
  systems today.

## User-visible behaviour

### Signed-in Home — stacked feed

Sections render top-down, and only if they have content. Empty
sections are hidden (never shown as empty states — that would
re-create the "Home is just the Calendar" smell).

1. **Your saved matches** — matches the user tapped Save on,
   starting from today and going forward 90 days. Highest-signal,
   usually 0–5 cards.
2. **Changes on your saved matches** — if a saved match has been
   moved (new `starts_at`), cancelled (`status = cancelled`), or
   had its venue swapped (`venue_id` changed), surface the change
   here with a one-tap "View match" link. **Always free, never
   premium** — people who don't see a change-of-venue alert because
   it's locked behind a paywall will blame the app for sending
   them to the wrong range.
3. **At clubs you follow** — upcoming matches at any `Organisation`
   the user follows, dedup'd against (1).
4. **In your sports** — upcoming matches in disciplines the user
   follows, dedup'd against (1) and (3). Scoped by `home_province`
   plus optional `second_province` so Precision Rifle in Pretoria
   doesn't show every WC match.
5. **Entries opened** _(premium only)_ — matches that flipped from
   `confirmed` to `entries_open` in the last 7 days. Push
   notification sent at the moment of flip; this section is the
   in-app persistence of that signal.
6. **Fallback** — if 1–5 are all empty: current generic "This
   weekend" / "Next 30 days" slices. Only ever shows for brand-new
   signed-in users before onboarding or users who have followed
   nothing.

### Anonymous / signed-out Home

Unchanged. Current "This weekend" + "Next 30 days" + Browse button.

### First-run onboarding

A single 2-step picker shown once immediately after sign-up:

1. **Pick your sports** — grid of all disciplines, user taps the
   ones they shoot. Writes to `user_discipline` pivot.
2. **Pick your province** — chip row of SA provinces, picks a
   primary (`home_province`); a second screen offers an optional
   `second_province`.

If the user skips the picker, Home falls through to the Fallback
section (generic calendar excerpt) until they follow something.
Hitting Me → Preferences lets them come back to it.

## Follow model

What the user can follow:

| Entity | Followable? | Notes |
|---|---|---|
| Club (`Organisation`) | ✅ | Includes national bodies (SAPRF, CTSASA, NRL Hunter SA, etc.) — they host the nationals, which is what people care most about. |
| Discipline | ✅ | Collapsed to parent name per the 2026-10 discipline cleanup — "Precision Rifle", not "PRS". |
| Range (`Venue`) | ⚠️ Low prominence | Button on the range page only, not in onboarding. Most matches are club-run, not range-run. |
| Shop (`Provider`) | ❌ Never in feed | Specials / stock alerts, if they ever ship, go as a separate paid listing product. Mixing commerce into the match feed would cheapen it. |
| Province | ❌ Not "followed" | Treated as a profile setting (`home_province` + `second_province`) rather than a follow, because it's a distance filter, not an entity. |

## Data model

### New tables

```php
// user_organisation — follow a club / federation
Schema::create('user_organisation', function (Blueprint $table) {
    $table->foreignId('user_id')->constrained()->cascadeOnDelete();
    $table->foreignId('organisation_id')->constrained()->cascadeOnDelete();
    $table->timestampTz('created_at');
    $table->primary(['user_id', 'organisation_id']);
});

// user_discipline — follow a discipline
Schema::create('user_discipline', function (Blueprint $table) {
    $table->foreignId('user_id')->constrained()->cascadeOnDelete();
    $table->foreignId('discipline_id')->constrained()->cascadeOnDelete();
    $table->timestampTz('created_at');
    $table->primary(['user_id', 'discipline_id']);
});

// user_venue — follow a range (low prominence)
Schema::create('user_venue', function (Blueprint $table) {
    $table->foreignId('user_id')->constrained()->cascadeOnDelete();
    $table->foreignId('venue_id')->constrained()->cascadeOnDelete();
    $table->timestampTz('created_at');
    $table->primary(['user_id', 'venue_id']);
});

// event_change_events — append-only log of material changes to events
Schema::create('event_change_events', function (Blueprint $table) {
    $table->id();
    $table->foreignId('event_id')->constrained()->cascadeOnDelete();
    $table->string('field'); // starts_at | ends_at | venue_id | status
    $table->json('from');    // {"value": "...", "cast": "datetime"}
    $table->json('to');
    $table->timestampTz('occurred_at');
    $table->index(['event_id', 'field']);
    $table->index('occurred_at');
});
```

### Columns on `users`

```php
Schema::table('users', function (Blueprint $table) {
    $table->string('home_province', 32)->nullable()->after('calendar_slug');
    $table->string('second_province', 32)->nullable()->after('home_province');
    $table->timestampTz('onboarded_at')->nullable();
});
```

`home_province` is nullable so existing accounts keep working. The
first-run picker populates it; the feed treats a null `home_province`
as "no geographic filter" and shows the user everything they follow.

### Change-tracking observer

`EventObserver::updating()` compares the dirty attributes against
`starts_at`, `ends_at`, `venue_id`, `status` and writes a row per
material change into `event_change_events`. Writes happen inside the
same transaction as the update, so there's no "the match changed but
we lost the event" window.

Dates ignored:
- `confirmed_at` moves don't count as "change" (it's a status flag,
  the user doesn't need to know when the host clicked Confirm).
- `updated_at` isn't a material change.

## API

### `GET /api/v1/home/feed`

Auth required. Returns the feed sections. 200 even for a brand-new
user with nothing followed (returns the fallback).

```json
{
  "sections": [
    {
      "key": "saved",
      "label": "Your saved matches",
      "events": [EventCard, ...]
    },
    {
      "key": "changes",
      "label": "Changes on your saved matches",
      "changes": [
        {
          "event": EventCard,
          "field": "starts_at",
          "from": "2026-11-07T08:00:00+02:00",
          "to":   "2026-11-14T08:00:00+02:00",
          "occurred_at": "2026-10-29T19:14:03+02:00"
        }
      ]
    },
    { "key": "followed_clubs", "label": "At clubs you follow",   "events": [...] },
    { "key": "followed_sports", "label": "In your sports",       "events": [...] },
    { "key": "entries_opened", "label": "Entries just opened",   "events": [...] },
    { "key": "fallback", "label": "This weekend",                "events": [...] }
  ]
}
```

- Each section runs its own query with its own eager-loads.
- Sections with zero items are **omitted entirely**, not returned
  with an empty `events` array.
- Fallback is only emitted when every other section is empty.
- Response is cached per-user for 60s to absorb pull-to-refresh
  bursts. Invalidated on any `event_change_events` write that
  references an event the user saves or whose host they follow —
  that's cheap because we join against the pivots.

### `POST /api/v1/me/follows/{type}/{id}`
### `DELETE /api/v1/me/follows/{type}/{id}`

`{type}` ∈ `organisations` | `disciplines` | `venues`.
Returns `{ followed: true | false }`. Idempotent.

### `PATCH /api/v1/me/preferences`

```json
{
  "home_province": "GP",   // or null
  "second_province": null  // or province code
}
```

Validated against the `Province` enum.

### Change-of-saved-match notifications

In-app notice via existing `App\Notifications\...` conventions; email
via Mailgun. Both free. Fire from the same place that writes the
`event_change_events` row.

## Mobile

### Home screen

`app/(tabs)/index.tsx` branches on `getAuthToken() !== null`:
- Anonymous → current `AnonymousHome` (unchanged).
- Signed-in → new `PersonalisedHome` that fetches `/api/v1/home/feed`
  and renders sections in server-provided order. Each section is a
  horizontal MatchCardRow with "See all →" that drops to the
  Calendar pre-filtered by that follow dimension.

### Follow buttons

- **Club page** (`app/clubs/[slug].tsx`): Follow / Following toggle
  in the header subtitle area, mirroring Save Match's styling.
- **Discipline tiles**: no longer surfaced on Find (dropped in the
  "Item 3 overlap" cleanup), but surfaced in the first-run picker
  and on Me → Preferences.
- **Range page**: Follow / Following toggle on the venue header. Not
  in onboarding.

### First-run picker

`app/onboarding/sports.tsx` + `app/onboarding/province.tsx`, shown
immediately after `/signup` returns a 201. User can skip either
step; `onboarded_at` is stamped on completion of step 2 regardless.

### Pre-SignIn Home gate

If a user is signed in but `onboarded_at IS NULL`, the Home tab
replaces its content with a one-time nudge: "Pick a sport and your
home province to personalise your Home →" that pushes to onboarding.
This is only ever seen once per account.

## Premium gating

| Feature | Tier | Rationale |
|---|---|---|
| Personalised feed | Free (signed-in) | Reason to sign up; drives adoption. |
| Change / cancel alerts on saved matches | **Free** | Safety-of-trust: don't let a free user drive to a cancelled match because the alert was paywalled. |
| iCal one-off export of saved matches | Free | Already essentially free (one `.ics` download). |
| Push notifications for new matches in followed clubs/disciplines | Premium | Effort-saver, not safety. |
| Push notification when `entries_open` on a followed match | Premium | Effort-saver. |
| Live iCal subscription URL (always up-to-date) | Premium | Effort-saver. |
| Entries-closing reminders | **Deferred** | No data (see below). |
| Partner-club discounts / early access | **Deferred** | Depends on clubs opting in. |

## Deferred: entries-close / entries-open dates

A 2026-10-25 audit (now in `php artisan` history) found:
- No `entries_closes_at` or `entries_opens_at` columns on `events`.
- Only 64% of upcoming matches have an `entry_url`; the remaining
  36% give a contact email or payment-instructions blob. Of those
  with an `entry_url`, almost all delegate entries to the host's
  own site (SAPRF, Impala, etc.) — so even if we had a close date
  column, we'd have no way to populate it without manual entry.

Decision from product discussion: **leave the schema alone, strike
"entries closing Friday" from the premium pitch** until platform-
entries adoption crosses roughly 50% and we have real data to drive
the reminder. The `status = entries_open` transition is enough to
run the premium push on today.

## Rollout order

Suggested branches (not committed yet — this is spec):

1. **Phase A — observe & record** (zero user-facing).
   - `EventObserver` + `event_change_events` table + the three
     material-field diffs.
   - No API, no mobile. Just start collecting the data so when the
     feed ships, "Changes on your saved matches" has 1–2 weeks of
     backlog to draw from.
2. **Phase B — follow + preferences backend.**
   - Pivots, columns on `users`, `/me/follows/*`, `/me/preferences`.
   - Unit tests for the pivots, feature tests for the endpoints.
3. **Phase C — `/home/feed` endpoint.**
   - All six section queries + the cache. Feature test per section.
4. **Phase D — Mobile.**
   - `PersonalisedHome` branch, follow buttons on club/range pages,
     onboarding screens, Me → Preferences editor.
5. **Phase E — Push + iCal + premium gate.**
   - Needs `expo-notifications` setup (already installed) + push
     token registration + the premium check hook.

Each phase is independently shippable. Phase A collects real data
even if Phases B–E slip.

## Open questions for Phase B

- Should `onboarded_at` be stamped on the first follow (implicit
  completion) or only after the picker is finished? Lean: on first
  follow — covers the user who dismisses the picker but still taps
  Follow on a club page.
- Should national-body follows be auto-added for every user based on
  their picked disciplines (e.g. picking Precision Rifle auto-follows
  SAPRF)? Lean: yes — avoids the "I picked Precision Rifle but see
  no PRS national matches" dead end. Flag as an auto-follow in
  `user_organisation` so an explicit unfollow sticks.

## Prerequisites not covered by this spec

- Push notifications infra on both platforms. `expo-notifications`
  is in the mobile `package.json` already but token registration
  and server-side send-via-FCM/APNs is not wired. Needs its own
  mini-spec.
- iCal generator for Phase E — small, but there's no existing
  calendar export route. Can share route scaffolding with the
  Admin desk's calendar export if that already exists.
