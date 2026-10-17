# ShootingSports platform simplification and desk access

Instructions for Cursor. Audit performed on the live site (shootingsports.co.za) on 17 Oct 2026, logged in as staff. Work through the phases in order. Each phase ends with Pest tests and must leave `php artisan test` green. Read the matching mockup in `docs/mockups/platform-mockups.html` before building any screen.

Stack rules: Laravel + Livewire + Filament + Pest, PostgreSQL. Target the latest stable Laravel, PHP and Filament majors; if `composer.json` is behind, do the upgrade as its own commit before Phase 1. Do not rename database tables or models in this work. Renames are labels only.

---

## 0. What the audit found

### Structure today

Two Filament panels. `/admin` (staff) has 21 nav items across 6 groups. `/desk` (match directors) has 5 items: Clubs & series, Claim a listing, Events, Articles, Magazine issues. A public `/login` already says "one login for shooters and match directors", but `/admin/login` and `/desk/login` still exist as separate Filament login screens.

Roles are booleans on `users`: `is_staff`, `is_md` plus `md_status`, and `plan` / `pro_until`. Match director requests arrive as rows in Enquiries (type "Md Signup").

### Bugs (fix in Phase 1)

| # | Where | What happens | Expected |
|---|---|---|---|
| B1 | Events, admin and desk | All-day and date-only events show **02:00** (e.g. "Sat 7 Nov 2026, 02:00", "Sun 7 Mar 2027, 02:00"). Midnight UTC is rendered in Africa/Johannesburg. Affects imported SAPRF rows and staff-created rows. | All-day events show the date only. Times are stored and displayed in `Africa/Johannesburg`. |
| B2 | SAPRF importer | "Rimfire PR22 GP 2-Day National Championship Match" has no end date, and venue is one string "Day 1 - Papaberg / Day 2 - Legends Adv Farm". | Importer sets `ends_at` when the source spans days. Multi-venue events either get a primary venue plus a note, or a flag for staff review. Never create a venue from a combined string. |
| B3 | Dashboard | Stat tile "41 Upcoming matches" links to the unfiltered Events list (150 rows). "11 Clubs & series" links to a list of 18. "Manage subscribers" links to the unfiltered Users list. | Every number links to a list that shows exactly that number. |
| B4 | Global search | Searching "Pretoria" returns Organisations and Providers only. | Events, Venues (ranges) and Users are searchable too. |
| B5 | Venues data | Duplicate group "Kraaifontein" has one record in Mpumalanga. "Taai Boskraal" has town = "Western Cape". | Add validation: town must not equal a province name; geocode check that lat/lng falls inside the selected province (warn, not block). |
| B6 | Desk, as staff | Desk home says "0 listings, 0 matches under your care" while Desk > Listings shows 18 and Desk > Events shows 150. | Desk queries are scoped to the signed-in user's memberships for everyone, staff included. See Phase 3. Confirm whether a real match director currently sees other clubs' records; treat as a security bug until proven otherwise. |
| B7 | Admin Events list | Default sort is newest-first, so the list opens on April 2027. Default 10 per page. | Default tab "Upcoming", sorted soonest first, 25 per page. |
| B8 | Inbound-only resources | Enquiries, Submissions, Claims and Media all show a "New ..." create button. | Remove create actions. These records come from the public site. |
| B9 | Raw columns | "Claimable type / Claimable id", "Attachable type / id", "Merged into type / id", "Day fee cents", "Federation organisation id", "Disk", "Bytes". | Human labels: the linked record's name with a link, money in rand, file size formatted. Hide storage columns by default. |
| B10 | Desk copy | Footer says "You are not a firearms dealer listing." Card says "Royal Flush-style series". | Plain copy, see mockup 3. |
| B11 | Desk forms | Event create exposes Slug and a Status field with Confirmed / Entries open. Organisation create exposes Slug. | Slug generated automatically. Publication (draft / pending review / published) is separate from event lifecycle status. |
| B12 | Articles and magazine issues | Created in /desk, no admin page to review them. | Admin gets a review queue for media partner content (Phase 2 Inbox). |

### Naming is inconsistent

The same thing has up to four names:

| Public site | Admin nav | Admin dashboard | Admin filters | Desk |
|---|---|---|---|---|
| Events | Events | Matches | Match | Events / matches |
| Clubs | Clubs & series (page title "Organisations") | Clubs & series | Organiser | Listings |
| Ranges | Venues | Ranges | Missing a range | Venue / range |
| Sports | Disciplines | Sports | Missing a sport | Disciplines |
| Industry | Providers | Industry | | |

Rule from now on: **use the public word everywhere a human reads it**: Events, Clubs, Ranges, Sports, Industry. Model and table names stay as they are (`Event`, `Organisation`, `Venue`, `Discipline`, `Provider`). Put the labels in one place, `lang/en/labels.php`, and reference them from every resource (`$modelLabel`, `$pluralModelLabel`, `$navigationLabel`, table column labels, filter labels, dashboard copy).

---

## Phase 1. Bugs and quick wins (no structural change)

1. **Timezone (B1).** Set `'timezone' => 'Africa/Johannesburg'` in `config/app.php` only if the codebase already stores local time; otherwise keep UTC in the database and set display timezone in Filament (`->timezone('Africa/Johannesburg')` on DateTime columns and pickers, or `FilamentTimezone::set()` in a service provider). For `all_day = true`, render with a date-only format everywhere (admin table, desk table, public calendar, iCal `VALUE=DATE`, JSON-LD `startDate` as `YYYY-MM-DD`). Write a one-off command `events:fix-all-day-times` that finds all-day events whose stored time is not local midnight and normalises them. Dry-run flag first.
2. **Importer end dates (B2).** In the SAPRF importer, parse the source end date. If the title contains "2-Day" / "3-Day" and no end date exists, set `ends_at = starts_at + (n-1) days` and flag the row `needs_review`. If venue text contains "Day 1" / "/", do not create or match a venue; leave venue null and attach the raw string to an `import_notes` field visible to staff.
3. **Dashboard links (B3).** Each stat tile and attention row links with the exact table filter that produced the count. Use a shared query scope (e.g. `Event::upcoming()`) for both the count and the list filter so they cannot drift. Remove "Manage subscribers" until a subscribers filter exists, or link to Users with `plan = pro` filter.
4. **Global search (B4).** Add `$recordTitleAttribute` and `getGloballySearchableAttributes()` to Event (title, host name, venue name), Venue (name, town) and User (name, email).
5. **Venue validation (B5).** Rule `NotAProvinceName` on town. A soft warning on save when lat/lng is outside the province bounding box (store bounding boxes in a config array).
6. **Events list defaults (B7).** Tabs: Upcoming (default), Needs attention, Past, All. Default sort `starts_at asc` on Upcoming. `->defaultPaginationPageOption(25)` on every admin table.
7. **Remove create actions on inbound resources (B8).** `canCreate(): false` on Enquiry, Submission, Claim, Media resources.
8. **Human columns (B9).** Replace polymorphic type/id pairs with a single "Record" column that shows the related name and links to its edit page. Money columns use `->money('ZAR', divideBy: 100)`. Hide Disk, Path, Mime, Bytes, Width, Height by default (`toggleable(isToggledHiddenByDefault: true)`).
9. **Venues list.** Visible by default: Photo (only if any exist, else hide), Name, Town, Province, GPS (tick or cross), Status, Verification. Everything else toggleable and hidden by default. Same treatment for Providers.
10. **Labels.** Create `lang/en/labels.php` and apply the naming rule above across both panels.

Tests: date formatting for all-day vs timed events; dashboard count equals filtered list count for each tile; inbound resources return 403 on `/create`.

---

## Phase 2. Simplify the admin panel

Target: 21 nav items down to 12, grouped by what staff are doing, not by table. See mockup 1 and 2.

```
Home                     (dashboard, renamed "Today")
Inbox            [badge] (single queue, tabs below)

Directory
  Events
  Clubs
  Ranges
  Industry
  Sports

Advertising              (one page: slots and placements)
Users

System                   (collapsed by default)
  Imports
  Settings               (flags, page pictures, email & Turnstile, email log as tabs)
```

What moves where:

| Today | After |
|---|---|
| Verification (own page) | A "Verification" tab on Clubs, Ranges and Industry lists (Unconfirmed / Ageing / Verified) plus a dashboard card. Bulk action "Mark verified" stays. |
| Enquiries, Submissions, Claims, Media | Tabs inside **Inbox**. Add tabs: Director requests, Articles. Badge on the nav item = total open items. |
| Venue duplicates (own page) | Header action "Find duplicates" on Ranges, opening the same merge screen. |
| Ad slots + Placements | One **Advertising** page. Placements list with a relation manager or a slots tab. |
| Flags, Users, Email & Turnstile, Page pictures, Email log | Users stays top-level. The rest become tabs on one **Settings** page under System. |
| Calendar imports | **Imports** under System. Each source card gets: last run, created / updated / skipped, a "View rows from this run" link (filtered Events list). |
| MD signups in Enquiries | Proper **Director requests** queue (Phase 3 data model). Approve / reject actions create the membership. |

Inbox build notes:
- Make Inbox a custom Filament page with Livewire tabs, each tab rendering the existing resource table (reuse the table definition, do not duplicate it). Use a static `table()` method on each resource so both the resource page and the Inbox tab call it.
- Each row has two primary actions only: Approve and Reject (with reason). "Open" goes to the record.
- Show the submitter, what changed (diff for corrections), and age in days. Sort oldest first.
- Nav badge: `getNavigationBadge()` returns the open count, colour danger when any item is older than 3 days.

Dashboard ("Today") build notes:
- Row 1: four tiles only: Inbox open, Events this week, Listings unconfirmed, Ads live. Each links to a filtered list.
- Row 2: "Fix these" list built from the existing attention rules (missing organiser, missing registration link, missing GPS, no long description), each with count, severity and a link with the filter applied.
- Row 3: Upcoming 7 days (compact list) and Recent activity (log of creates/edits from desk users).
- Drop Quick actions grid; the same links already live in the nav and the global search. Keep a single "Add event" primary button in the header.

Tests: each nav item resolves 200 for staff; Inbox counts equal the sum of tab counts; old URLs (`/admin/verification-dashboard`, `/admin/venue-duplicates`, `/admin/ad-slots`, `/admin/flags`, `/admin/manage-mail-settings`, `/admin/manage-page-pictures`, `/admin/email-logs`) redirect 301 to their new home.

---

## Phase 3. Desk for different users, with no route into admin

### Principle

One account, one login at `/login`. What you can do depends on **capabilities**, not on which login screen you used. Staff work in `/admin`. Everyone who manages something on the directory works in `/desk`. Shooters never see a panel; their account lives on the public site (`/my-calendar`, `/my-log`, `/settings/notifications`).

| User type | Can sign in to | Desk sections |
|---|---|---|
| Shooter (default) | Public account pages only | None. "Desk" link hidden. |
| Match director / club admin | `/desk` | My clubs, Events, Entries (when on-site entries are on) |
| Industry business | `/desk` | My business, Adverts (impressions and clicks for their placements), Enquiries received |
| Media partner | `/desk` | Articles, Magazine issues |
| Staff | `/admin` and `/desk` | Desk shows only their own memberships, same as everyone else. Use "View as" (impersonation) to support a user. |

A person can hold several capabilities at once. Desk shows the union.

### Data model

Replace the booleans with memberships. Keep the old columns until the migration is verified, then drop them in a later release.

```php
// users
role: enum('user','staff','admin') default 'user'   // replaces is_staff

// organisation_user  (clubs, series, federations a user manages)
id, organisation_id, user_id,
role: enum('owner','editor'),
status: enum('pending','approved','rejected'),
approved_by (nullable user_id), approved_at, timestamps
unique(organisation_id, user_id)

// provider_user  (industry listings a user manages)
id, provider_id, user_id, role: enum('owner','editor'), status, approved_by, approved_at, timestamps

// users.is_media_partner boolean  (or a media_partners table if partners become companies)
```

Data migration:
- `is_staff = true` becomes `role = 'staff'`. Paul's account becomes `admin`.
- Each approved claim (`claims.status = approved`, `claimable_type = Organisation`) becomes an `organisation_user` row with role owner, status approved. Same for providers.
- `is_md = true, md_status = approved` users with no claim: create a Director requests item for staff to link them to a club; do not guess the club.
- Existing "Md Signup" enquiries become Director requests.

User model helpers:

```php
public function isStaff(): bool { return in_array($this->role, ['staff','admin']); }
public function managedOrganisationIds(): Collection   // approved only
public function managedProviderIds(): Collection
public function hasDeskAccess(): bool {
    return $this->hasVerifiedEmail() && (
        $this->organisations()->wherePivot('status','approved')->exists()
        || $this->providers()->wherePivot('status','approved')->exists()
        || $this->is_media_partner
        || $this->isStaff()
    );
}
```

### Panel gates (the hard boundary)

`User implements FilamentUser`:

```php
public function canAccessPanel(Panel $panel): bool
{
    return match ($panel->getId()) {
        'admin' => $this->isStaff(),
        'desk'  => $this->hasDeskAccess(),
        default => false,
    };
}
```

This is what keeps desk users out of admin. Filament checks it on every panel request, including Livewire update calls. Do not rely on hiding links.

Also:
- Remove `->login()` from both panel providers. Set `->loginRouteSlug()` aside and redirect `/admin/login` and `/desk/login` to `/login` (301). The unified `/login` sends staff to `/admin`, desk users to `/desk`, everyone else to `/my-calendar`, respecting `intended()`.
- A signed-in user without desk access who hits `/desk` gets a friendly page ("Your desk opens once a club or business is linked to your account") with buttons Claim a club, List your business. Implement by catching the 403 for the desk panel and rendering a Blade view, not a raw 403.
- A desk user who hits `/admin` gets a plain 404 (do not confirm the panel exists).

### Scoping inside desk (the soft boundary that matters most)

Every desk resource overrides `getEloquentQuery()`:

```php
// Desk EventResource
public static function getEloquentQuery(): Builder
{
    return parent::getEloquentQuery()
        ->whereIn('organisation_id', auth()->user()->managedOrganisationIds());
}
```

Apply the same to Organisation (by id), Provider (by id), Placement (provider in managed providers, read-only), Article and MagazineIssue (`author_id = auth()->id()` or partner id). **No staff bypass in desk.** That fixes B6.

Form option lists are scoped the same way. The "Host club" select on the desk event form lists only managed organisations, and the server validates it too:

```php
Select::make('organisation_id')
    ->label(__('labels.club'))
    ->options(fn () => Organisation::whereIn('id', auth()->user()->managedOrganisationIds())->pluck('name','id'))
    ->rule(Rule::in(auth()->user()->managedOrganisationIds()))
    ->required()
```

Add Laravel policies (`EventPolicy`, `OrganisationPolicy`, `ProviderPolicy`, `ArticlePolicy`) with a `before()` that returns `true` for staff **only when the current panel is admin**:

```php
public function before(User $user): ?bool
{
    if (Filament::getCurrentPanel()?->getId() === 'admin' && $user->isStaff()) return true;
    return null;
}
public function update(User $user, Event $event): bool
{
    return $user->managedOrganisationIds()->contains($event->organisation_id);
}
```

Register policies so both panels use them. Desk resources do not get delete; desk users can cancel an event (status change) instead.

### Publication flow

Separate two fields that are mixed today:

- `publication`: `draft` | `pending_review` | `published` | `rejected` (who can see it)
- `status`: `planned` | `confirmed` | `entries_open` | `full` | `postponed` | `cancelled` | `completed` (what is happening with the event)

Desk users can set `status` freely on their own events. They can only move `publication` from draft to pending_review. Staff publish from Inbox. Add `organisations.trusted` (boolean, staff-only). Events from a trusted club go straight to published, and still appear in Inbox under "Published by trusted clubs" for a quick look.

The same applies to club and business profile edits from desk: changes to name, province, contact details create a Submission (correction) for review unless the listing is trusted. Descriptions and banners can go live immediately, with Media moderation catching images.

### Desk navigation by capability

Each desk resource implements `shouldRegisterNavigation()` / `canViewAny()` from capabilities:

```
Home
My clubs            (if any approved organisation membership)
Events              (if any approved organisation membership)
My business         (if any approved provider membership)
Adverts             (if provider has placements)
Articles            (if media partner)
Magazine issues     (if media partner)
Account             (link out to public /settings/notifications)
```

Desk home (mockup 3) shows: pending items waiting on staff, next three events with a completeness bar (banner, registration link, range with GPS, description), and one primary button per capability (Add event, Edit business, Write article).

### Event form in desk

Replace the 39-field single form with a 4-step wizard (Filament `Wizard`), mockup 4:

1. **Basics**: Club (scoped select), Title, Sport(s), Level, Type.
2. **When and where**: Start date, End date (optional), All day toggle (on by default; time fields appear when off), Range (search existing; "Range not listed" opens a small create modal that creates a pending venue).
3. **Entry**: Fee, member fee, registration link or "take entries on this site", organiser email, max entries.
4. **Extras and publish**: Banner upload, beginner-friendly flags (as chips, not 11 long checkboxes with paragraphs; put the descriptions in hint tooltips), description, then "Submit for review" (or "Publish" if trusted).

Slug is generated. No Status field at creation (defaults to planned). Edit uses tabs with the same four sections.

### Joining as a match director or business

- `/register` already asks "What do you plan to do". After email verification, a director goes to `/desk` and sees the no-access page with **Claim a club** (search published clubs) and **Add a new club** (creates a draft club with them as pending owner). Both create a Director request in admin Inbox.
- Approving a request sets the membership to approved and emails the user. They can then add events.
- Owners can invite editors by email from My clubs (creates a pending membership that the owner approves, no staff step).

### Tests for Phase 3 (all must exist)

```
it('blocks a match director from /admin with 404')
it('blocks a shooter from /desk and shows the no-access page')
it('lets a staff user into both panels')
it('redirects /admin/login and /desk/login to /login')
it('only lists events from the director\'s own clubs in desk')
it('returns 403 when a director opens another club\'s event edit URL in desk')
it('rejects a desk event saved with an organisation_id the director does not manage')
it('does not let staff see other clubs\' records inside desk')
it('puts a new desk event into pending_review unless the club is trusted')
it('hides Events from a supplier-only desk user')
it('lets a supplier see impressions and clicks for their own placements only')
it('rejects a direct Livewire update call to an admin component from a desk user')
```

Use model factories with states: `->staff()`, `->director(Organisation $org)`, `->supplier(Provider $p)`, `->mediaPartner()`.

---

## Phase 4. Clean-up after release

- Drop `is_staff`, `is_md`, `md_status` once production data is migrated and checked.
- Remove the old admin pages that now redirect, after one month of 301s.
- Add "View as user" (impersonation) for staff, logging start and stop to the activity log.
- Six-monthly listing confirmation email (already planned) uses `organisation_user` owners as recipients.

---

## Definition of done

- Admin nav has 12 items or fewer and uses the public vocabulary.
- Every dashboard number matches the list it links to.
- No all-day event displays a time anywhere.
- A match director, a supplier and a shooter each see only what the access table above allows, proven by the Phase 3 tests.
- One login screen.
