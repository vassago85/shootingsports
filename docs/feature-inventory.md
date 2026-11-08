# Feature inventory

Audit date: 8 November 2026, updated the same day after the launch punchlist. Every row is taken from code that was read for this pass. Line numbers refer to that code. This is an inventory, not a product pitch.

## How to read the tiers

The five labels below are the ones this inventory uses. Desk capabilities are called out separately because they are **not** a subscription.

| Tier | Meaning in this codebase |
| --- | --- |
| Public | No login. |
| Free account | Any signed-in user. Plan defaults to Free. |
| Paid / Pro | `User::isPro()` (plan `pro` and `plan_expires_at` null or in the future), or a gate that calls `allows()` / `isPro()`. Staff (`users.is_staff`) bypass these gates. |
| Desk role | Signed-in user with an approved club, shop, range, or media-partner seat. Not a paid plan. `/desk` itself opens for every signed-in user; the resources hide themselves. |
| Staff only | `/admin`. `User::canAccessPanel()` allows the admin panel only when `users.is_staff` is true. |
| Advertiser | A listing or placement staff have marked as paid inventory. There is no self-serve ad checkout. |

Two switches sit on top of almost everything:

- **Coming-soon gate** defaults off (`COMING_SOON`, `config/coming-soon.php:19`). When on, `EnsureComingSoonAccess` (`app/Http/Middleware/EnsureComingSoonAccess.php:24-41`) redirects web requests to `/coming-soon` unless the path is allowlisted, the route is in `coming-soon.public_routes` and `COMING_SOON_EXPOSE_PUBLIC` is on, or the user is staff or `is_match_director`. `/admin` and `/desk` use a different middleware stack and are not redirected.
- **Pro sales** default off (`PRO_FEATURES`, `config/plans.php:35`). Caps still apply. Checkout, the trial button, and upgrade CTAs stay hidden until the flag is on (`app/Livewire/Upgrade.php:64`, `app/Services/StartProTrial.php:68`).

Pro means `plan === pro` and the expiry is open (`app/Models/Concerns/HasPlan.php:35-49`). An expired Pro row behaves as Free with no nightly job. Staff short-circuit the paid gates (`app/Providers/AppServiceProvider.php:208-223`).

Status:

- **Live** — routed and the code path does the thing.
- **Partial** — reachable, but a flag hides it, a piece is missing, or only some of the promised behaviour exists.
- **Stub** — model, copy, or config only. No user-facing path that does the work.

## Summary

### Public site

| Feature | Area | Tier | Status | Enforced by |
| --- | --- | --- | --- | --- |
| Home | public | Public | Live | `routes/web.php:74` `HomeController` |
| National calendar and filters | public | Public | Live | `routes/web.php:75` `CalendarController`; Livewire `CalendarFilter` |
| Month calendar | public | Public | Live | `routes/web.php:84` `CalendarMonth` |
| Map of upcoming matches | public | Public | Live | `routes/web.php:92` `MapController` |
| Weekend / province / discipline answer pages | public | Public | Live | `routes/web.php:76-83`, `389-391` `AnswerPageController` |
| Discipline index, show, province, about | public | Public | Live | `routes/web.php:95-109`; unpublished disciplines 404 at `DisciplineController.php:78` |
| Discipline rankings (results recorded on this site) | public | Public | Live | `routes/web.php:102`; `Rankings.php:11-40` reads `event_results` |
| Division hubs (handgun, rifle, shotgun, air rifle) | public | Public | Live | `routes/web.php:96-98`; unknown slug 404 at `DivisionController.php:19` |
| Clubs index and club page | public | Public | Live | `routes/web.php:111`, `125`; unpublished 404 at `OrganisationController.php:198` |
| Province + discipline club landings | public | Public | Live | `routes/web.php:112-114` |
| Federation page | public | Public | Live | `routes/web.php:129`; non-federation 404 at `OrganisationController.php:164` |
| Ranges index and range page | public | Public | Live | `routes/web.php:131-136` |
| Industry directory, category, province, listing | public | Public | Live | `routes/web.php:138-145`; unpublished or non-public category 404 at `ProviderController.php:119`, `168-169` |
| Featured / verified listings sort above free ones | public | Advertiser | Live | Staff set `providers.tier` / `venues.tier`. Sort: `Provider.php:271`, `Venue.php:235-239`. Public logo only when tier is Featured: `Provider.php:115-129`. No payment sets the tier. |
| Which-sport-fits-me wizard | public | Public | Live | `routes/web.php:148` `FindYourDiscipline` |
| Stories feed (`/feed`) | public | Public | Live | `routes/web.php:149` `FeedController`. Published articles only. No per-article URL. |
| Article snippets on home, discipline, and match pages | public | Public | Live | `HomeController.php:63`, `DisciplineController.php:246`, `EventController.php:33-44` |
| Magazine index and flipbook | public | Public | Live | `routes/web.php:155-156`. Drafts 404 unless the URL is a signed desk preview: `MagazineIssueController.php:39-43`. Nav item hidden until at least one issue is published: `PublicNav.php:77-87`. The public credit is `publisherLabel()` (linked organisation, else free-text publisher), not the account that uploaded the file. |
| Magazine issue that requires an account | public | Free account | Live | `reader_access === 'account'` redirects guests to login: `MagazineIssue.php:83-86`, `MagazineIssueController.php:47-48`. Not a Pro check. |
| Magazine PDF download | public | Public | Live | Per issue, when `pdf_download_enabled` and a file exists: `MagazineIssue.php:103-106` |
| Match page (non-draft) | public | Public | Live | `routes/web.php:180`; drafts 404 at `EventController.php:19`. With no banner, description, or team-entry form the left column is omitted (`matches/show.blade.php` class `is-facts-only`). |
| Recent results block on a discipline page | public | Public | Live | `resources/views/public/disciplines/show.blade.php` section `results`; data is recorded results, not a federation ranking |
| iCal: event, discipline, club | feed | Public | Live | `routes/web.php:99-100`, `115`. Unpublished / draft 404: `IcalController.php:20`, `29`, `38` |
| Personal iCal (`/calendars/{token}.ics`) | feed | Public | Live | `routes/web.php:215-217` `IcalController::shooter` looks up `users.calendar_token`. The old `/shooters/{slug}/calendar.ics` returns 404 (`web.php:218-220`). The owner resets the token from `/me/calendar`. No Pro check. |
| Public shooter page (`/shooters/{slug}`) | public | Public | Live | `ShooterCalendarController::show`. Private unless the user opts in (`ProfileVisibility`, default private). Anyone else, including guests, gets 404; the owner and staff can open it. The page does not render equipment, upcoming entries, or the calendar token. |
| Embeddable calendar, script, docs, oEmbed | embed | Public | Live | `EmbedController`. A national embed stays public. A `club` or `organisation` embed is 404 unless that organisation's club subscription is current. The snippet on the club and federation pages is hidden until then. Range embeds are unchanged. |
| Ad click redirect (`/go/{placement}`) | public | Advertiser | Live | Active placement only: `PlacementClickController.php:12` |
| Advertise rate card and enquiry | public | Public | Live | `routes/web.php:217` `EnquiryController::advertise`. Prices in `config/advertising.php`. Enquiry only; no checkout. |
| Contact, listing enquiry, public reply thread | public | Public | Live | `routes/web.php:218-232`. Thread is token-addressed, not login. |
| Claim a club, range, or match | public | Public | Live | `routes/web.php:126-128` `SubmitClaim`. Published listings only (`SubmitClaim.php:164`, `186`, `201`). Staff approve in `/admin`. |
| Claim a supplier listing | public | Public | Live | `routes/web.php:146` `ClaimListing` |
| How to claim, privacy, terms | public | Public | Live | `routes/web.php:233-235` |
| Sitemaps, robots.txt, llms.txt | public | Public | Live | `routes/web.php:237-254` |
| Coming-soon page and interest form | public | Public | Live | `routes/web.php:66-72`. The gate that forces everyone else here defaults off. |
| App mockup gallery | public | Public | Live | `routes/mockups.php:5` `/mockups/apps` |
| JSON event dump (`/events.json`) | feed | Public | Live | `routes/web.php:77` |
| Legacy WordPress URL redirects | public | Public | Live | `RedirectLegacyWordPressUrls` in `bootstrap/app.php:43` |

### Accounts (no payment required)

| Feature | Area | Tier | Status | Enforced by |
| --- | --- | --- | --- | --- |
| Sign in, register, forgot / reset password | public | Public | Live | `routes/web.php:260-265` guest middleware. One form; old `/directors/register` and `/suppliers/register` 301 to `/register` (`web.php:271-273`). |
| Log out | public | Free account | Live | `routes/web.php:316-322` `auth` |
| Email verification | /me | Free account | Live | `routes/web.php:280-288`. Required before supplier onboarding (`web.php:306`). |
| My home (`/me`) | /me | Free account | Live | `routes/web.php:193-195` `auth`. Manage cards only when `hasDeskAccess()` (`User.php:236-250`, view `public/me/index.blade.php:26`). |
| Save a match to my calendar | public | Free account | Paid / Pro over the cap | `SavedEvent` creating observer calls `EnforcesPlanLimits::assert` for `saved_events`. Free cap 3, Pro unlimited (`config/plans.php`). Web (`SaveToCalendar`) opens the upgrade prompt. The app (`SavedEventController`) returns 403 with code `saved_events_limit`. Saving a match already on the list does not count again. |
| My calendar page | /me | Free account | Live | `routes/web.php` `my-calendar`, `auth`. Saved matches, the cap counter, the secret iCal link, and “reset my calendar link” come before equipment. Profile visibility (private by default) is set here. Past matches older than the plan window are hidden, not deleted: `ShooterCalendarController` `limit('history_months')`. Free window is 12 months. |
| Follow a club, range, or discipline | public | Free account | Paid / Pro over the cap | Creating a follow asserts the cap: `Follow.php:20-29`. Free cap 3, Pro unlimited (`config/plans.php:38`, `50`). Over cap opens the upgrade prompt (`FollowButton.php:78-82`). Guests are sent to login (`FollowButton.php:46-48`). |
| Save a calendar filter | public | Free account | Paid / Pro over the cap | `SavedSearch.php:15-24`. Free cap 1 (`config/plans.php:39`). UI: `CalendarFilter.php:244-276` and the same path in `CalendarMonth`. |
| Attendance log (up to the free slots) | /me | Free account | Paid / Pro over the cap | `routes/web.php:343-349` `auth`. Cap on create: `AttendedEvent.php:41-56`. Free slots 3 (`config/plans.php:46`). UI also checks `withinLimit` in `LogAttendance.php:72` and `AddManualAttendance.php:66`. Gate `log-attendance` is any signed-in user (`AppServiceProvider.php:221`). |
| Equipment list on my calendar | /me | Free account | Live | `ManageEquipment` on `/me/calendar`, below the saved-match list. Not shown on the public shooter page. No plan check. |
| Dedicated-status settings form | /me | Paid / Pro | Live | `DedicatedStatus::viewerCanEdit` is staff or `isPro()`. A Free user sees the form read-only, with an upgrade prompt when `plans.pro_enabled` is on. Save and clear do nothing otherwise. |
| Notification preferences | /me | Free account | Live | `routes/web.php:378-380` `auth` `NotificationPreferences` |
| One-click email unsubscribe / resubscribe | public | Public | Live | `routes/web.php:376-377`. Token on the user, not a login. |
| Apply to join a club | public | Free account | Live | `ApplyForMembership`. The club must accept applications and `allowsClubFeature('members')`. |
| Enter a match on the platform | public | Free account | Live | `EnterEvent.php:27-40`. Event must be open for platform entries. Paystack path is per event (`EntryCollection::Paystack`), not a shooter plan. |
| Submit a team entry | public | Public | Live | `SubmitTeamEntry`. No login required. Turnstile runs before the 5-per-IP limit. Event must be open for team entries. Blank Turnstile keys pass only in local and testing. |
| My memberships and invoices | /me | Free account | Live | `routes/web.php:360-366` `auth`. Record must be linked to the user or the same email: `MyMembershipDetails.php:115`, `MembershipPaymentController.php:106`. |
| Pay a club invoice (Paystack or “I’ve paid by EFT”) | /me | Free account | Live | `MembershipPaymentController.php:24-44`. Online pay also requires the club flag `membership_online_payments`. This is the member paying the club, not buying Pro. |
| Submit a match for staff approval | public | Free account | Partial | `routes/web.php:167-174` `auth` + `verified`. Draft until staff approve. Thanks page only for the creator or staff (`web.php:170`). |
| List a business | public | Free account | Live | `routes/web.php:306-314` `auth` + `verified`. Listing stays Pending until staff publish. Edit requires `ProviderPolicy::update` (`EditListing.php:51`): staff or `claimed_by`. |
| Save a magazine issue to my library | public | Free account | Live | `SaveMagazineIssue` on the issue page. Library: `routes/web.php:201-203` `auth` `MagazineLibrary`. No Pro check found. |
| Report a correction | public | Public | Live | `POST /corrections` (`ReportCorrectionController`). Match, club, and range pages. Reasons: wrong date, wrong province, wrong venue, other, plus free text. Turnstile, honeypot, throttle 5/minute. Creates a pending `Submission` for `/admin`. |
| Match-director request at signup | public | Free account | Partial | Stored as `md_requested_at`. Does not grant desk rights. `is_match_director` stays false until staff approve (`User.php:120-130`). Banner on my calendar (`mine.blade.php:62-66`). |
| Supplier onboarding flag at signup | public | Free account | Live | `needsSupplierOnboarding()` (`User.php:115-118`) sends them to the listing form after verify. |

### Paid / Pro

Caps live in `config/plans.php:37-55`. Sales UI is behind `plans.pro_enabled` (default false). The caps themselves are on today.

| Feature | Area | Tier | Status | Enforced by |
| --- | --- | --- | --- | --- |
| Unlimited follows | /me, app | Paid / Pro | Live | Free 3 / Pro null. `Follow` creating observer via `EnforcesPlanLimits`. The unused `unlimited-follows` gate was removed. |
| Unlimited saved searches | public | Paid / Pro | Live | Free 1 / Pro null. `SavedSearch` creating observer. |
| Unlimited saved matches | /me, app | Paid / Pro | Live | Free 3 / Pro null. `SavedEvent` creating observer. Same path on the website and the API. |
| Calendar history beyond 12 months | /me | Paid / Pro | Live | `ShooterCalendarController` `limit('history_months')`. The unused `full-history` gate was removed. |
| Unlimited attendance-log rows | /me | Paid / Pro | Live | Free 3 / Pro null. `AttendedEvent.php:56`. |
| CSV and printable attendance export | /me | Paid / Pro | Live | `ShooterLogController.php:91` and `:278` `can('export-attendance-log')`. Gate is staff or `allows('export')` (`AppServiceProvider.php:222`). Free `export` is false (`plans.php:41`). Route is still `auth` only; free users get 403 from the controller, and the log page shows an upgrade control instead (`log.blade.php:71`). |
| Dedicated-status tracker widget | /me | Paid / Pro | Live | `User::hasDedicatedTracker()` requires a known body and `isPro()` (`User.php:633-638`). Widget on `/me` only if Pro (`me/index.blade.php:124`). |
| Dedicated-status PDF / print | /me | Paid / Pro | Live | `ShooterLogController.php:147-148` and `:205-206`: `hasDedicatedTracker()` and `can('export-attendance-log')`. |
| Dedicated-cycle reminder email | feed | Paid / Pro | Live | Scheduled `dedicated:send-reminders` (`routes/console.php:36`). Command emails users who have the tracker fields; the tracker itself is Pro (`User.php:633-638`). Opt-out: `EmailCategory::DedicatedReminders`. |
| 30-day Pro trial (no card) | /me | Paid / Pro | Partial | `StartProTrial::isEligible` returns false while `plans.pro_enabled` is false (`StartProTrial.php:68`). One trial per account (`:74`). Sets plan Pro for 30 days. |
| Buy Pro (Paystack monthly / annual) | /me | Paid / Pro | Partial | `/upgrade` is `auth`. Checkout and cancel no-op while `plans.pro_enabled` is false. When the flag is on, `/me` shows the live upgrade card (prices and “Upgrade to Pro”) instead of the waitlist. Prices `plans.php` (R299/year, R29/month). Plan codes `config/services.php`. Callback, webhook, cancel, and expiry are covered by HTTP-faked tests. A live Paystack test-mode charge has not been run here: the local secret and both plan codes are blank. |
| Cancel Pro | /me | Paid / Pro | Partial | `web.php:330-332` `auth` `PaystackController::cancel`. Same sales flag. |
| Staff “Grant Pro comp” | /admin | Staff only | Live | `UserResource.php:317`. Sets plan without Paystack. Bypasses `pro_enabled`. |
| Pro trial emails (T-3 and T+0) | feed | Paid / Pro | Partial | Scheduled `pro:trial-nudges`. No one is trialing while sales are off, so the job currently has nothing to send unless a comp/trial row already exists. Opt-out: `EmailCategory::TrialNudges`. |
| Email the Pro waitlist when sales open | feed | Public | Live | `php artisan pro:notify-waitlist` (`NotifyProWaitlist`). One mail per Pro-waitlist enquiry that is not still awaiting confirmation. Skips addresses whose user has turned off product updates. `--dry` lists without sending. |

### Desk (`/desk`)

Any signed-in user can open `/desk` (`User.php:387-391` returns true; Filament `Authenticate` still requires login, `DeskPanelProvider.php:123-125`). A shooter with no seat sees the empty landing (`DeskDashboard.php:33-36`). Club and range lists, and their sidebar badges, count the user’s own seats (including `claimed_by`). Staff with no seat see nothing there; the full lists are `/admin`. Desk events still return every row for `is_staff`, so a staff user who creates a match can open the edit page. Other desk resources (members, invoices, guest days, enquiries, placements, articles, magazine issues) still short-circuit to every row for staff.

| Feature | Area | Tier | Status | Enforced by |
| --- | --- | --- | --- | --- |
| Desk home (director / industry / media / none) | /desk | Desk role | Live | `DeskDashboard`. Mode from `User::deskHomeMode()` (`User.php:400-408`). |
| My clubs | /desk | Desk role | Live | `OrganisationResource::canViewAny` director or staff. The query is the user’s linked clubs or `claimed_by`, including for staff. Edit: `OrganisationPolicy::update` staff or match director / admin / editor. |
| Claim another club | /desk | Desk role | Live | Nav: director or staff (`ClaimListing.php:33-39`). Creates a claim; staff approve it. |
| Events for my clubs | /desk | Desk role | Live | `EventResource::canViewAny` director or staff. Non-staff rows are limited to host clubs. Staff still see every event, so creating one does not 404 on edit. Create/update/delete: `EventPolicy` staff or a `canManageEvents` seat. |
| Entries list (read only) | /desk | Desk role | Live | Shown when a managed event accepts platform or team entries, and also when a director's club is unpaid, so the lock page is reachable. Rows are limited to paid host clubs. |
| Members | /desk | Desk role | Live | Admin or secretary seat, and the club's subscription is current. An unpaid club still shows the nav item, which opens a lock page. Creating or editing a member is rejected in `GuardsClubSubscription`. |
| Invoices | /desk | Desk role | Live | Same member-managing seat and the same club subscription. Online payment of an invoice also requires `allowsClubFeature('invoices')`. |
| Guest days | /desk | Desk role | Live | Same seat and `allowsClubFeature('guest_days')`. |
| Membership reports | /desk | Desk role | Live | `MembershipReports::canAccess` is still the seat check. The figures and CSV only include clubs on the subscription. Unpaid-only secretaries get the lock page. |
| My ranges | /desk | Desk role | Live | `VenueResource::canViewAny` staff or `isRangeManager()`. The query is approved `venue_user` seats or `claimed_by`, including for staff. Update: `VenuePolicy::update`. No create page; claim flow is public. |
| Enquiries about my business | /desk | Desk role | Live | Industry desk user or staff (`EnquiryResource.php:111-116`). Read only (`:137-149`). Scoped to managed providers (`:104-108`). |
| Adverts (impressions and clicks) | /desk | Advertiser | Live | Read only. Nav appears only when this user has at least one placement (`PlacementResource.php:89-107`). Staff place the booking in `/admin`. |
| “My business” and “Advertise with us” nav | /desk | Desk role | Live | Visible only for industry users who are not also directors (`DeskPanelProvider.php:128-135`). Advertise link is the public `/advertise` page. |
| Articles | /desk | Desk role | Live | Staff or `is_media_partner` (`ArticleResource.php:39-44`). Non-staff see only their rows (`:84-88`). Publishing is a datetime on the form. |
| Magazine issues | /desk | Desk role | Live | Staff or `is_media_partner`. Same own-rows scope. The form has a publisher organisation and a free-text publisher name. “Uploaded by” stays on the form and is not the public credit. |
| Open admin (user menu) | /desk | Staff only | Live | Menu item `visible` when `is_staff` (`DeskPanelProvider.php:105-109`). |

`hasDeskAccess()` (`User.php:236-250`) is what `/me` and the app use to show Manage cards: media partner, or an approved organisation, provider, or venue membership. It does not by itself open `/admin`.

### Admin (`/admin`)

Panel login is Filament’s. A signed-in non-staff user who opens `/admin` is redirected, not shown a 403 (`AuthenticateAdminPanel.php:43-62`). The panel itself allows only `users.is_staff` (`User.php:386`). Pages that also declare `canAccess()` repeat that boolean. None of these screens check `isPro()` or `plans.pro_enabled`.

Sidebar groups are Home-level items (Today, Inbox, Advertising, Users) plus Directory and System (`AdminPanelProvider.php:69-75`). Several resources stay routed with the sidebar entry turned off; they are reached from Inbox, Settings, or a header action.

| Feature | Area | Tier | Status | Enforced by |
| --- | --- | --- | --- | --- |
| Today | /admin | Staff only | Live | `StaffDashboard::canAccess` (`StaffDashboard.php:58-60`). Counts pending clubs, shops, claims, submissions, enquiries, and submitted draft matches. |
| Inbox | /admin | Staff only | Live | `Inbox::canAccess` (`Inbox.php:73-76`). Tabs cover enquiries, claims, submissions, media, MD requests, draft articles, and draft matches. Approve / Reject on an MD request (`Inbox.php:317`, `:346`). “Publish now” on a draft article (`Inbox.php:423`). |
| Users | /admin | Staff only | Live | `UserResource`. Toggles for match director, media partner, and staff (`UserResource.php:80-87`). Actions: Resend confirmation (`:256`), Approve MD request (`:276`), Reject MD request (`:293`), Grant Pro comp (`:317`, months field `:322`). Subscription fields on the form are read-only (`:115-118`). |
| Events | /admin | Staff only | Live | Directory label from `lang/en/labels.php`. Create and edit the public match, including on-site entries and Paystack fee collection (`EventResource.php:280-283`). “Approve match” (`:498`) and “Confirm dates” (`:516`). Relation managers: individual entries, team entries, and results (`EventResource.php` relations). Results entered here are what the public rankings page reads. |
| Clubs | /admin | Staff only | Live | `OrganisationResource`. `status` select publishes or holds the public page (`OrganisationResource.php:128-130`). Memberships relation manager is on this resource. |
| Ranges | /admin | Staff only | Live | `VenueResource`. Day fee, GPS, photos, logo, and the same `tier` enum as shops (`VenueResource.php:183` area, day fee label `:257`). “Geocode from address” on edit (`EditVenue.php:32`). “Find duplicates” opens the merger (`ListVenues.php:30`). |
| Sports | /admin | Staff only | Live | `DisciplineResource`. Published toggle (`DisciplineResource.php:229`), parent sport, divisions, about copy, and federation link. Unpublished sports 404 on the public site. |
| Industry | /admin | Staff only | Live | `ProviderResource`. Staff set `tier` (free / verified / featured). Logo helper text says the picture shows only while Featured (`ProviderResource.php:158`). This is the switch for the public enhanced listing. It is not wired to Paystack. |
| Advertising placements | /admin | Staff only | Live | Nav label “Advertising” (`PlacementResource.php:40`). Staff set slot, dates, headline, image, click URL, active flag, and invoiced rate in cents (`PlacementResource.php:85`). Creating a row here is what makes a public advert and the desk “Adverts” stats exist. |
| Ad slot catalogue | /admin | Staff only | Live | `AdSlotResource`. Sidebar hidden (`AdSlotResource.php:42-45`). Opened by “Ad slot catalogue” on the placements list (`ListPlacements.php:23`). Holds page, slot, and price in cents (`AdSlotResource.php:65`). Price is a catalogue figure, not a charge. |
| Enquiries | /admin | Staff only | Live | Sidebar hidden; Inbox and the resource URL `/admin/enquiries` still resolve (`EnquiryResource.php:47-49`). “Mark handled” (`EnquiryResource.php:163`). On the edit page: Reply (`EditEnquiry.php:33`) and Forward to organiser (`EditEnquiry.php:58`). Includes Pro-waitlist rows. |
| Claims | /admin | Staff only | Live | Sidebar hidden (`ClaimResource.php:41-44`). Staff cannot create one (`:46-48`). Pending rows have approve and reject (`ClaimResource.php:121`, `:141`). Approving an organisation claim asks for a desk role and defaults to Admin (`:129-131`). That seat is what later opens `/desk`. |
| Submissions (corrections) | /admin | Staff only | Live | Sidebar hidden. Staff cannot create one in the panel. The public “Report a correction” form creates the pending row. Approve merges into a listing; reject stores a reason that is not emailed. |
| Media moderation | /admin | Staff only | Live | Sidebar hidden (`MediaResource.php:41-44`). Approve / reject a pending upload (`MediaResource.php:143-149`). Staff cannot create a row (`:46-50`). |
| Calendar imports | /admin | Staff only | Live | `ManageCalendarImports::canAccess` (`ManageCalendarImports.php:42-45`). Per-source frequency, and “Run now” (`:62`). This is what puts federation fixtures on the public calendar. |
| Settings | /admin | Staff only | Live | `Settings::canAccess` (`Settings.php:34-37`). Links only: Flags, Email & Turnstile, Page pictures (`Settings.php:44-62`). |
| Flags | /admin | Staff only | Live | `FlagResource`, sidebar hidden (`FlagResource.php:39-41`). Beginner-friendly flags that appear on public events. |
| Page pictures | /admin | Staff only | Live | `ManagePagePictures::canAccess` (`ManagePagePictures.php:45-48`). Sidebar hidden. Hero images on the public site. |
| Email and Turnstile | /admin | Staff only | Live | `ManageMailSettings::canAccess` (`ManageMailSettings.php:51-54`). Mailgun domain, from address, API key, Turnstile keys, and “Send test email” (`:158`). |
| Email log | /admin | Staff only | Live | `EmailLogResource`, System group. Create is off (`EmailLogResource.php:37`). A record of mail the app sent. |
| Verification queue | /admin | Staff only | Partial | Still routed at `/admin/verification-dashboard`. Removed from the nav (`VerificationDashboard.php:32-35`). The same states are filters on the club, range, and industry lists. |
| Venue duplicate merger | /admin | Staff only | Live | Routed, not in the nav (`VenueDuplicates.php:33-36`). Opened from “Find duplicates” on ranges. |
| Data-quality report | /admin | Staff only | Live | Scheduled `data-quality:report` (`console.php:44`). Written to the log. Not a public page. |
| Product backlog on the public calendar | public | Staff only | Stub | `config/product-backlog.php:19-23` (“Firearm licence renewal reminders”, audience `pro`). Rendered only when `is_staff` (`calendar.blade.php:21`). No reminder job. |

### App (Expo)

Tabs are Home, Calendar, Find, Me for guests and signed-in users (`apps/mobile/app/(tabs)/_layout.tsx:7-10`). Auth is a Sanctum bearer token (`routes/api.php:35-41`, `54`). There is no in-app purchase; Pro opens `https://shootingsports.co.za/upgrade` in the browser (`me.tsx:42-46`, `:284`).

| Feature | Area | Tier | Status | Enforced by |
| --- | --- | --- | --- | --- |
| Sign in / sign out | app | Public / Free account | Live | `POST /api/v1/auth/login` throttle 5/min (`api.php:35-37`). Logout `auth:sanctum`. |
| National events, disciplines, venues, clubs, shops, map | app | Public | Live | `api.php:44-50`. No auth. |
| Match detail | app | Public | Live | `GET /api/v1/events/{slug}` and screen `apps/mobile/app/matches/[slug].tsx` |
| Find: events map, clubs, ranges, shops | app | Public | Live | `find.tsx:16-19` and modes at `:71-76`. Sports mode was removed in that file’s comment. |
| Home calendar | app | Public | Live | Generic national list. |
| Personalised home (saved + followed) | app | Free account | Partial | Flag `EXPO_PUBLIC_HOME_PERSONALISED` defaults off (`apps/mobile/src/flags.ts:32-34`). API `GET /api/v1/me/feed` is built and auth-gated (`api.php:70`). |
| Profile, plan pill, limits | app | Free account | Live | `GET/PATCH /api/v1/me` (`api.php:55-56`). `is_pro` from `UserResource.php:67`. |
| Saved matches | app | Free account | Paid / Pro over the cap | `api.php` `auth:sanctum`. `SavedEventController` uses `SavedEvent::create`, so the same `saved_events` cap applies. Over the cap: 403 JSON `code` `saved_events_limit`. |
| Follows | app | Free account | Paid / Pro over the cap | `api.php:61-65`. Same `Follow` observer. Over cap returns 403 (`MeFollowController.php:88-97`). |
| Follow suggestions | app | Free account | Live | `api.php:75-76` |
| Push device token | app | Free account | Live | `api.php:88-89` |
| Manage: capabilities, event list, status change, enquiries, placement stats | app | Desk role | Live | `api.php:94-110` `auth:sanctum`. Event status change calls `EventPolicy::update` (`ManagedEventController.php:68`). Enquiries and placements scoped to managed listings. Heavier editing stays on the website (controller docblocks). |
| Pro upsell | app | Free account | Partial | Shown for Free users. Opens the website. Does not check `plans.pro_enabled` before showing the link (`me.tsx:284`). |

### Feeds, mail, and jobs people notice

| Feature | Area | Tier | Status | Enforced by |
| --- | --- | --- | --- | --- |
| Federation calendar import (SAPRF, MPSA, CGPSA, Vektor, SAPF) | feed | Public | Live | `calendar:import`. Sources with `mode: import`. MPSA maps a venue from the town named in the cell, including inside a longer address (`TownProvince::fromText`). Unknown towns still default to Mpumalanga. `VenueProvinceCorrection` rewrites a stored province when the town, address, or name contains an unambiguous town, and clears a non-staff pin whose nearest province disagrees. SAPSA is `mode: sell` and is not imported. NRL22SA has no importer; the 14 November 2026 title “Course of Fire 08/2027” is the source season label (COF 8 of 2026/2027), not a date parse. |
| Push: match cancelled, entries open | app | Free account | Live | `EventObserver.php:61-66`. Recipients are users who saved that match. Not follows, not saved searches. |
| Email: entries just opened | feed | Free account | Live | Same observer, only if `EmailPreferences::canSend` for `MatchAlerts` (`EventObserver.php:141`). |
| Email: club application received / decided | feed | Free account | Live | `ApplyForMembership.php:188`, desk member table `ClubMembersTable.php:409` |
| Email: team entry to captain and organiser | feed | Public | Live | `SubmitTeamEntry`, after Turnstile and the rate limit. |
| Email: enquiry received, reply, forward | feed | Public | Live | `EnquiryReceivedMail`, `RecordEnquiryReply.php:30`, `EditEnquiry.php:87` |
| Email: listing / claim / new registration to staff | feed | Staff only | Live | `StaffInbox`, `NewRegistrationMail`, claim and supplier mails |
| Email: MD request approved / rejected | feed | Free account | Live | `MdRequestApproved`, `MdRequestRejected` |
| Email: Pro trial ending / ended | feed | Paid / Pro | Partial | `SendProTrialNudges`. See Pro section. |
| Email: dedicated-cycle reminder | feed | Paid / Pro | Live | `SendDedicatedCycleReminders.php:105` |
| Email: club renewal reminder | feed | Free account | Live | `club-members:send-renewal-reminders` (`console.php:30`). Sent to the member email. |
| Club membership lapse | /desk | Free account | Live | `club-members:lapse` (`console.php:29`). Changes member status; members see it on `/my-memberships`. |
| Coming-soon confirm mail | public | Public | Live | `ComingSoonInterestController` |
| Weekly digest | /me | Free account | Stub | Preference and copy exist (`EmailCategory.php:48`). No scheduled command sends it. `routes/console.php` has no digest entry. |
| Product-update emails | /me | Free account | Stub | Preference column and settings UI. No sender found. |
| Email: Pro waitlist, sales open | feed | Public | Live | `pro:notify-waitlist`. Respects the product-updates preference. |

## 4. Payment, subscription, and billing

There is no Laravel Cashier and no PayFast. Paystack is the only provider. Three different charges share that client. Only the first one changes a shooter’s plan.

### 4.1 Shooter Pro

Prices and caps are `config/plans.php`. Keys and plan codes are `config/services.php:97-108`. `php artisan paystack:setup-plans` creates the Paystack plans from `plans.pricing` (R299/year, R29/month, `plans.php:74-84`).

Routes (`routes/web.php:329-334`):

- `GET /upgrade` requires `auth`. `Upgrade` refuses to start a trial or a checkout while `plans.pro_enabled` is false (`Upgrade.php:64`, `:94`).
- `POST /upgrade/cancel` requires `auth`. It asks Paystack to disable the subscription (`PaystackController.php:111-148`).
- `GET /paystack/callback` is not behind `auth`. It verifies the transaction and applies the plan.
- `POST /paystack/webhook` is CSRF-exempt (`bootstrap/app.php:71-73`). A bad signature is ignored.

What a successful Pro charge writes (`SubscriptionApplier.php:40-49`): `plan = pro`, `plan_billing_cycle`, `plan_expires_at` (paid_at plus one month or one year), Paystack customer, authorisation, and subscription codes, and it clears `plan_cancelled_at`. A later `charge.success` extends `plan_expires_at` from the current expiry (`:59-73`). `subscription.disable` and `subscription.not_renew` set `plan_cancelled_at` and leave Pro in place until expiry (`:81-85`). `invoice.payment_failed` only logs (`PaystackController.php:391-398`). It does not downgrade the user.

A failed charge does not remove Pro. Expiry is `HasPlan::isPro()` seeing `plan_expires_at` in the past (`HasPlan.php:35-49`). There is no nightly downgrade job.

Staff can grant the same plan with no Paystack codes: “Grant Pro comp” calls `applyManualGrant` (`SubscriptionApplier.php:93-104`, action `UserResource.php:317`). That ignores `plans.pro_enabled`.

The 30-day no-card trial (`StartProTrial.php`) also sets `plan = pro` and `plan_expires_at`. `isEligible()` returns false while `plans.pro_enabled` is false (`StartProTrial.php:68`), and a user only gets one trial (`:74`).

**What Pro gates today**, whether the row came from Paystack, a comp, or a trial:

| Cap or feature | Free | Pro | Where it is enforced |
| --- | --- | --- | --- |
| Follows | 3 | unlimited | `Follow` creating observer |
| Saved searches | 1 | unlimited | `SavedSearch` creating observer |
| Saved matches | 3 | unlimited | `SavedEvent` creating observer |
| Calendar history shown | 12 months | all | `ShooterCalendarController` `limit('history_months')` |
| Attendance-log rows | 3 | unlimited | `AttendedEvent.php:56` |
| Log CSV and print | no | yes | `can('export-attendance-log')` at `ShooterLogController.php:91` and `:278`. Gate is staff or `allows('export')` (`AppServiceProvider.php:222`). |
| Dedicated-status tracker and its PDF | no | yes | `User.php:633-638`, `ShooterLogController.php:147-148` and `:205-206` |

Staff (`is_staff`) pass the gates in `AppServiceProvider.php:213-223` without a Pro row.

**What Pro does not gate:** the public calendar, directories, match pages, federation iCal feeds, the embed, follows under the cap, saved matches under the cap, the magazine (an “account” issue is login-only), club membership, desk seats, and `/admin`. A personal iCal feed is a secret link, not a Pro feature.

`plans.pro_enabled` (default false, `plans.php:35`) hides the buy button and the trial. It does not lift the caps. A Free user still cannot create a fourth follow.

### 4.1a Club subscription

Separate from shooter Pro. Columns live on `organisations` (`plan` free|club, `plan_expires_at`, `plan_cancelled_at`, billing cycle, Paystack customer and subscription codes). `Organisation::isPaid()` is the expiry check. There is no nightly downgrade. Feature flags and the pricing placeholders are `config/club-plans.php`. `CLUB_PLANS_ENABLED` defaults false. Prices and `PAYSTACK_CLUB_PLAN_ANNUAL` / `PAYSTACK_CLUB_PLAN_MONTHLY` are unset on purpose.

Free for every club: claim or create the club, edit the profile and logo, create and edit events (status, banner, beginner flags), link out to the organiser's own entry system, and the public club page.

The subscription unlocks members (including public applications and CSV import), invoices, guest days, membership reports, on-site platform and team entries, Paystack entry-fee collection, the desk entries list, and a club-scoped embed. Enforcement is `GuardsClubSubscription`, `EventObserver::saving`, `openForPlatformEntries` / `openForTeamEntries`, and `EmbedController`. Desk nav stays visible and opens `club-plan-locked` per unpaid club. Subscribe is `/clubs/{slug}/subscribe` when the sales flag is on; otherwise the lock links to `/contact`.

Checkout calls `PaystackClient::initializeTransaction` with metadata `purpose=club_plan`. The callback and `charge.success` webhook run `ClubSubscriptionApplier` and do not change the director's shooter plan. `subscription.disable` for a code stored on the organisation sets `plan_cancelled_at` and leaves access until `plan_expires_at`. A live charge cannot be run until the placeholder prices and plan codes are filled in. Entry fees still use `initializeOneOffTransaction` and are not settled to a subaccount.

### 4.2 Match entry and club invoices

These set payment status on an entry or an invoice. They do not set `users.plan`.

- A match can take the public entry fee on Paystack when `entry_collection` is Paystack (`EnterEvent.php:54`). The webhook sees `metadata.purpose = event_entry` and marks that entry paid (`PaystackController.php:307-310`). It returns before the Pro applier.
- A member can pay an issued club invoice when the club has `membership_online_payments` (`MembershipPaymentController.php:40-55`). The webhook sees `purpose = club_membership` and records the invoice (`PaystackController.php:313-316`). “I’ve paid by EFT” does not charge a card (`:75`).
- Platform fee, VAT treatment, and who bears Paystack’s fee are `config/paystack.php`.
- A club can store a Paystack subaccount (`OrganisationPayoutAccount`) and an “accept online payments” toggle. `PaystackClient::initializeSplitTransaction` can attach that subaccount and a flat platform fee. Nothing in the live checkout calls it. `EnterEvent::startPayment` and `MembershipPaymentController::paystack` both call `initializeOneOffTransaction`, which charges the platform merchant and does not pass a subaccount. The webhook marks the entry or invoice paid and does not transfer the club’s share. Terms still say the platform does not automatically pay the club out, because that sentence is still true.

### 4.3 Advertising

`config/advertising.php` says the rate card is display-only and no payment provider is wired. Products and the figures in that file: enhanced listing R399/month, discover placement R1 800/month, discipline exclusive R3 500/month, home leaderboard R2 500/month, calendar leaderboard R2 000/month. The newsletter mention was removed until a digest mailer exists. The public commitment says advertising and optional Pro fund the free calendar and directory.

A public advert exists when staff save an active `Placement` (slot, dates, image, click URL, invoiced rate). Click-through is `/go/{placement}` and 404s when the placement is inactive (`PlacementClickController.php:12`).

“Enhanced” on a shop or range is `tier = featured`, set on the admin form. Featured rows sort first (`Provider.php:271`, `Venue.php:235-239`). A shop logo is public only at that tier (`Provider.php:115-129`). The Paystack webhook never writes `tier`. Paying for Pro does not buy a listing tier, and buying a listing (which cannot be done in-app) would not grant Pro.

## 5. Copy versus enforcement

### 5.1 Described as Pro, paid, or already sent — and not enforced that way

| What the copy says | Where | What the code does |
| --- | --- | --- |
| “Season exports” / “Season export is a Pro feature.” | `UpgradePrompt.php`, `mail/pro-trial-ended.blade.php` | Gate `export-season` is defined (`AppServiceProvider.php`) and has no caller. There is no season-export route. The export that exists is the attendance log. `/upgrade`, `/me`, and the terms now say “log export”, not “season exports”. |
| “Household up to four.” | `UpgradePrompt.php`, `plans.php` | `/me` and `/upgrade` no longer say this. The API still returns the number. `EnforcesPlanLimits` lists a trigger. No model creates a household, and nothing calls `assert('household_profiles', ...)`. |
| Match alerts fire for a saved search or a follow | `EmailCategory.php:47` | `EventObserver.php:61-66` notifies users who saved that match, and only when status becomes Cancelled (push) or Entries open (push, plus email if Match alerts is on, `:141`). Follows and saved searches do not send mail. |
| “A Monday summary” weekly digest | `EmailCategory.php:48` | The preference is stored. `routes/console.php` has no digest command. |
| Product updates, “never more than one a month” | `EmailCategory.php:49` | No general product-update mailer. `pro:notify-waitlist` is the one sender, and it skips users who have turned this preference off. |
| Firearm licence renewal reminders, audience Pro | `config/product-backlog.php:21-23` | Shown to staff on `/calendar` (`calendar.blade.php:21`). No job. |
| Trial ended: extras “stay visible but are read-only” | `mail/pro-trial-ended.blade.php:14` | Creates over the cap throw `PlanLimitExceeded`. Existing follows, searches, and log rows are not marked read-only. The user can still delete them. The “3 slots, delete one to add another” line in the same mail (`:15`) does match `AttendedEvent.php:56`. |
| App “Pro” link | `apps/mobile/app/(tabs)/me.tsx:284` | Always opens `https://shootingsports.co.za/upgrade`. It does not read `plans.pro_enabled`. With the flag off, that page is the waitlist (`upgrade.blade.php`), not checkout. |

Register copy that offers a 30-day trial is inside `@if (config('plans.pro_enabled'))` (`register.blade.php:93-98`). That one is gated. It is not a mismatch while the flag is off.

`/me`, `/upgrade`, and terms section 7 now use the same Pro list: unlimited saved matches, unlimited follows and saved searches, full calendar and log history, log export, and the dedicated-status tracker. Terms still say the platform does not automatically pay the club out. Entry fees and club invoices are one-off charges to the platform merchant, not a subaccount split.

Upgrade prompt headlines still describe a purchase (“Go Pro”, “Pro removes the 3-follow cap”) in `UpgradePrompt`. While `pro_enabled` is false the detail is replaced with “Pro is on hold”. The “Go Pro now” button is the other branch, when Paystack keys and the flag are both set.

### 5.2 Enforced in code, with weak or no matching sales copy

| What is enforced | Where | Copy gap |
| --- | --- | --- |
| Saved matches older than 12 months are omitted for Free | `ShooterCalendarController.php:29` | A cut-off row exists on my calendar (`mine.blade.php:118-123`, “Earlier matches are in your Pro history” in `upgrade-prompt.blade.php:7`). The public match page, the iCal feed, and `/upgrade`’s hold paragraph do not say this. |
| Fourth follow, second saved search, fourth saved match, and fourth log row are rejected | `Follow`, `SavedSearch`, `SavedEvent`, `AttendedEvent` | `/me/calendar` shows the saved-match counter. The prompt those failures open still becomes “Pro is on hold” while sales are off. |
| Attendance export button is an upgrade prompt for Free | `log.blade.php:71-79` | The button is labelled “Export for renewals”, not “Pro”. The prompt it opens (`export_attendance_log`, `UpgradePrompt`) is the copy. The route itself returns 403. |
| Magazine “account” issues require login, not Pro | `MagazineIssue.php:83-86` | No price and no plan check. A signed-in Free user can read it. |
| Member register, invoices, guest days, and reports need an admin or secretary seat | `OrganisationUserRole.php:39-41`, `ClubMemberResource.php:67-72` | Not on `/upgrade`. A match-director or editor seat can run events (`OrganisationUserRole.php:28-30`) and cannot open the member register. |
| `/admin` is `users.is_staff`, not `User::isStaff()` | `User.php:386` versus `:166-170` | `isStaff()` is also true when `role` is Staff or Admin. The panel check uses the boolean column only. |
| Featured sort and logo | `Provider.php:115-129` | Sold on `/advertise`. The directory does not mark the row as paid. Staff can set the tier with no payment. |
| Coming-soon gate | `EnsureComingSoonAccess.php:24-41` | Default off. When on, guests and ordinary shooters lose the public site except the allowlist. Staff and `is_match_director` pass. It is not a plan. |

## Planned or unfinished, found in code

| Item | Where | What exists |
| --- | --- | --- |
| Pro checkout and trial hidden until `PRO_FEATURES` is true | `config/plans.php` | Caps, `/upgrade`, webhook, cancel, and the waitlist are built. `/me` shows the buy card only while the flag is on. `pro:notify-waitlist` mails the waitlist. A live Paystack test-mode charge still needs the secret and plan codes. |
| Club subscription prices and Paystack plan codes | `config/club-plans.php`, `config/services.php` | Placeholders. `CLUB_PLANS_ENABLED` defaults false. Checkout refuses until both prices and plan codes are set. |
| Personalised app home | `apps/mobile/src/flags.ts:32-34` | API feed exists. App flag defaults off. |
| Weekly digest | `EmailCategory.php:26`, `routes/console.php` | Opt-in flag only. |
| Product-update mail | `EmailCategory.php:27` | Opt-in flag. The only sender is `pro:notify-waitlist`, which honours it. |
| Household profiles (up to 4 on Pro) | `config/plans.php` | Config and an API field. No household records. `/me` and `/upgrade` no longer sell it. The upgrade prompt still can. |
| Season export | `AppServiceProvider.php` gate `export-season` | Gate only. No exporter. Not used by the attendance-log export. |
| Firearm licence renewal reminders | `config/product-backlog.php:21` | Staff-visible backlog line. |
| Hunting / big game | `DeskPanelProvider.php:70` “Hunting / Big Game stay parked.” Morph map in `AppServiceProvider.php:191-196` (`HuntingProperty`, `Outfitter`, `BookingRequest`, `Species`, `QuoteRequest`, `Listing`) | No routes, no Filament resources, no public pages. |
| Saved-search and follow match alerts | `EmailCategory.php` | Copy only. Observer notifies saved events. |
| Date / venue change alerts | `EventObserver.php` | Comment says they are a follow-up. Not sent. |
| Calendar sources marked `sell` (CTSASA, SAPSA, NRAPA, IDPA, SA Hunters) | `config/calendar_sources.php` from line 51 | Listed for staff. Not imported. Notes say sell them the embed. |
| Coming-soon as the public face | `config/coming-soon.php:19` | Built, default off. |
| Verification dashboard | `VerificationDashboard.php:26-35` | Still reachable, dropped from the nav. |
| App sports browse on Find | `find.tsx:46-49` | Removed on purpose. Discipline filter lives on Calendar. |
