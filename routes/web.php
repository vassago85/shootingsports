<?php

use App\Enums\ProviderCategory;
use App\Enums\Province;
use App\Http\Controllers\CalendarController;
use App\Http\Controllers\CalendarMonthController;
use App\Http\Controllers\ComingSoonInterestController;
use App\Http\Controllers\DisciplineController;
use App\Http\Controllers\DivisionController;
use App\Http\Controllers\EmailVerificationController;
use App\Http\Controllers\EmbedController;
use App\Http\Controllers\EnquiryController;
use App\Http\Controllers\EventController;
use App\Http\Controllers\FeedController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\IcalController;
use App\Http\Controllers\LlmsTxtController;
use App\Http\Controllers\MagazineIssueController;
use App\Http\Controllers\MagazineReadController;
use App\Http\Controllers\MapController;
use App\Http\Controllers\MeController;
use App\Http\Controllers\MembershipPaymentController;
use App\Http\Controllers\OrganisationController;
use App\Http\Controllers\PaystackController;
use App\Http\Controllers\PlacementClickController;
use App\Http\Controllers\ProviderController;
use App\Http\Controllers\RobotsController;
use App\Http\Controllers\ShooterCalendarController;
use App\Http\Controllers\ShooterLogController;
use App\Http\Controllers\SitemapController;
use App\Http\Controllers\StaticPageController;
use App\Http\Controllers\UnsubscribeController;
use App\Http\Controllers\VenueController;
use App\Livewire\Auth\Login;
use App\Livewire\Auth\Register;
use App\Livewire\FindYourDiscipline;
use App\Livewire\Listings\SubmitClaim;
use App\Livewire\MagazineLibrary;
use App\Livewire\Matches\SubmitMatch;
use App\Livewire\MyMembershipDetails;
use App\Livewire\MyMemberships;
use App\Livewire\Settings\DedicatedStatus;
use App\Livewire\Settings\NotificationPreferences;
use App\Livewire\Suppliers\ClaimListing as SupplierClaimListing;
use App\Livewire\Suppliers\CreateListing as SupplierCreateListing;
use App\Livewire\Suppliers\EditListing as SupplierEditListing;
use App\Livewire\Upgrade;
use App\Models\Event;
use App\Models\Provider;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

$provincePattern = implode('|', array_map(fn (Province $province) => $province->urlSlug(), Province::cases()));
$categoryPattern = implode('|', array_map(fn (ProviderCategory $category) => $category->urlSlug(), ProviderCategory::cases()));

// Coming-soon landing shown while `config('coming-soon.enabled')` is
// true. Path is allowlisted in EnsureComingSoonAccess so it renders
// even when the gate is on; when the flag is off the page is still
// reachable directly (harmless, and useful for previewing the design).
Route::view('/coming-soon', 'public.coming-soon')->name('coming-soon');
Route::post('/coming-soon/interest', [ComingSoonInterestController::class, 'store'])
    ->middleware('throttle:10,1')
    ->name('coming-soon.interest');
Route::get('/coming-soon/confirm/{token}', [ComingSoonInterestController::class, 'confirm'])
    ->where('token', '[A-Za-z0-9]{64}')
    ->name('coming-soon.confirm');

Route::get('/', HomeController::class)->name('home');
Route::get('/calendar', CalendarController::class)->name('calendar');
Route::get('/calendar/month', CalendarMonthController::class)->name('calendar.month');

/*
 * UX audit cool-factor: province-clustered map of upcoming matches.
 * The dedicated /map route is the canonical surface; a legacy
 * ?view=map query on /calendar redirects here so any external
 * link shape reaches the same page.
 */
Route::get('/map', MapController::class)->name('map');
Route::get('/calendar/map', fn () => redirect()->route('map'));

Route::get('/disciplines', [DisciplineController::class, 'index'])->name('disciplines.index');
Route::get('/divisions/{division}', [DivisionController::class, 'show'])
    ->where('division', 'handgun|bolt-action-rifle|self-loading-rifle|shotgun|air-rifle')
    ->name('divisions.show');
Route::get('/disciplines/{discipline:slug}/calendar.ics', [IcalController::class, 'discipline'])->name('ical.discipline');
Route::get('/disciplines/{discipline:slug}/about', [DisciplineController::class, 'about'])->name('disciplines.about');
Route::get('/disciplines/{discipline:slug}/rankings', [DisciplineController::class, 'rankings'])->name('disciplines.rankings');
Route::get('/disciplines/{discipline:slug}/{province}', [DisciplineController::class, 'redirectLegacyProvince'])
    ->where('province', $provincePattern)
    ->name('disciplines.province');
Route::get('/disciplines/{discipline:slug}', [DisciplineController::class, 'show'])->name('disciplines.show');

Route::get('/clubs', [OrganisationController::class, 'index'])->name('clubs.index');
Route::get('/clubs/{province}/{discipline}', [DisciplineController::class, 'landing'])
    ->where('province', $provincePattern)
    ->name('clubs.landing');
Route::get('/clubs/{organisation:slug}/calendar.ics', [IcalController::class, 'organisation'])->name('ical.organisation');

// Legacy /clubs redirects for ranges that were seeded as clubs. Each of
// these slugs now lives only in `venues` — the ghost `organisations`
// row was removed in the 2026_09_15 reclassification migration. Kept
// explicit (not data-driven) so an extra ghost is a two-line PR, not
// an extra DB lookup on every /clubs/{slug} request.
Route::redirect('/clubs/muletech-ridge-range', '/ranges/muletech-ridge-range', 301);
Route::redirect('/clubs/dwarskloof-shooting-range', '/ranges/dwarskloof-shooting-range', 301);

Route::get('/clubs/{organisation:slug}', [OrganisationController::class, 'show'])->name('clubs.show');
Route::get('/claim/{type}/{slug}', SubmitClaim::class)
    ->whereIn('type', ['club', 'range', 'match'])
    ->name('listings.claim');
Route::get('/federations/{organisation:slug}', [OrganisationController::class, 'federation'])->name('federations.show');

Route::get('/ranges', [VenueController::class, 'index'])->name('ranges.index');
// Plain-string param instead of implicit `{venue:slug}` binding so
// the controller can resolve aliases and 301 old slugs onto the
// canonical URL. Route generation stays the same:
// `route('ranges.show', $venue->slug)` still works.
Route::get('/ranges/{slug}', [VenueController::class, 'show'])->name('ranges.show');

Route::get('/suppliers', [ProviderController::class, 'index'])->name('suppliers.index');
Route::get('/suppliers/{category}/{province}', [ProviderController::class, 'category'])
    ->where(['category' => $categoryPattern, 'province' => $provincePattern])
    ->name('suppliers.province');
Route::get('/suppliers/{category}', [ProviderController::class, 'category'])
    ->where('category', $categoryPattern)
    ->name('suppliers.category');
Route::get('/supplier/{provider:slug}', [ProviderController::class, 'show'])->name('suppliers.show');
Route::get('/supplier/{provider:slug}/claim', SupplierClaimListing::class)->name('suppliers.claim');

Route::get('/find', FindYourDiscipline::class)->name('find');
Route::get('/feed', FeedController::class)->name('feed');

// Targets magazine flipbook. Index lists published issues; show
// renders the page-flip reader plus a translate-friendly text view.
// Drafts 404 to the public, unless the request carries a signed
// preview link generated from the Desk resource.
Route::get('/magazines', [MagazineIssueController::class, 'index'])->name('magazines.index');
Route::get('/magazines/{issue}', [MagazineIssueController::class, 'show'])->name('magazines.show');
Route::post('/magazines/{issue}/read', [MagazineReadController::class, 'store'])
    ->middleware('throttle:60,1')
    ->name('magazines.read');

// Bare /matches and /events are not listing surfaces — /calendar is.
Route::redirect('/matches', '/calendar', 301);
Route::redirect('/events', '/calendar', 301);
// Director event intake. Registered before /events/{event} so
// "submit" is not captured as an event slug. The event is a draft
// until staff approve it from /admin.
Route::middleware(['auth', 'verified'])->group(function (): void {
    Route::get('/events/submit', SubmitMatch::class)->name('events.submit');
    Route::get('/events/submit/{event:slug}/thanks', function (Event $event) {
        abort_unless($event->created_by === auth()->id() || auth()->user()?->is_staff, 403);

        return view('public.matches.submitted', ['event' => $event]);
    })->name('events.submit.thanks');
});
Route::permanentRedirect('/matches/submit', '/events/submit');
Route::get('/matches/submit/{slug}/thanks', function (string $slug) {
    return redirect()->route('events.submit.thanks', $slug, 301);
});

Route::get('/events/{event:slug}', [EventController::class, 'show'])->name('events.show');
Route::get('/matches/{slug}', function (string $slug) {
    return redirect()->route('events.show', $slug, 301);
})->where('slug', '^(?!submit$).+');

// Shooter home. Every account is a shooter first; the page shows the
// Manage block only when hasDeskAccess() is true (club, shop, range,
// or media partner). The /me/* sub-routes host what used to live at
// /my-calendar, /my-log, and /settings/notifications; the old URLs
// 301 to the new ones so bookmarks and emailed links keep working.
// Route names stay the same (my-calendar, my-log, settings.notifications)
// so every existing route() caller resolves to the new URL with no
// bounce — only humans typing the old URL hit the redirect.
Route::get('/me', [MeController::class, 'show'])
    ->middleware('auth')
    ->name('me');

Route::get('/me/calendar', [ShooterCalendarController::class, 'mine'])
    ->middleware('auth')
    ->name('my-calendar');

Route::get('/me/library', MagazineLibrary::class)
    ->middleware('auth')
    ->name('my-library');
Route::redirect('/my-calendar', '/me/calendar', 301);
Route::get('/shooters/{shooter}/calendar.ics', [IcalController::class, 'shooter'])->name('ical.shooter');
Route::get('/shooters/{shooter}', [ShooterCalendarController::class, 'show'])->name('shooters.show');

Route::get('/embed/calendar', [EmbedController::class, 'calendar'])->name('embed.calendar');
Route::get('/embed/calendar.js', [EmbedController::class, 'script'])->name('embed.script');
Route::get('/embed', [StaticPageController::class, 'embedDocs'])->name('embed.docs');
Route::get('/oembed', [EmbedController::class, 'oembed'])->name('oembed');

Route::get('/go/{placement}', PlacementClickController::class)
    ->middleware('throttle:60,1')
    ->name('placements.click');

Route::get('/advertise', [EnquiryController::class, 'advertise'])->name('advertise');
Route::get('/contact', [EnquiryController::class, 'create'])->name('contact');
Route::get('/enquire/{type}/{id}', [EnquiryController::class, 'listing'])
    ->where(['type' => 'organisation|provider|venue|event', 'id' => '[0-9]+'])
    ->name('enquiries.listing');
Route::post('/enquiries', [EnquiryController::class, 'store'])
    ->middleware('throttle:10,1')
    ->name('enquiries.store');
Route::get('/enquiries/thanks', [EnquiryController::class, 'thanks'])->name('enquiries.thanks');
Route::get('/enquiries/thread/{token}', [EnquiryController::class, 'thread'])
    ->where('token', '[A-Za-z0-9]+')
    ->name('enquiries.thread');
Route::post('/enquiries/thread/{token}', [EnquiryController::class, 'reply'])
    ->where('token', '[A-Za-z0-9]+')
    ->middleware('throttle:10,1')
    ->name('enquiries.thread.reply');
Route::get('/claim', [StaticPageController::class, 'claim'])->name('claim');
Route::get('/privacy', [StaticPageController::class, 'privacy'])->name('privacy');
Route::get('/terms', [StaticPageController::class, 'terms'])->name('terms');

Route::get('/sitemap.xml', [SitemapController::class, 'index'])->name('sitemap');
Route::get('/sitemaps/{type}-{page}.xml', [SitemapController::class, 'chunk'])
    ->where([
        'type' => 'pages|events|organisations|venues|disciplines|providers|magazines',
        'page' => '[1-9][0-9]*',
    ])
    ->name('sitemap.chunk');
Route::get('/sitemaps/pages.xml', [SitemapController::class, 'pages'])->name('sitemap.pages');
Route::get('/sitemaps/events.xml', [SitemapController::class, 'events'])->name('sitemap.events');
Route::get('/sitemaps/organisations.xml', [SitemapController::class, 'organisations'])->name('sitemap.organisations');
Route::get('/sitemaps/venues.xml', [SitemapController::class, 'venues'])->name('sitemap.venues');
Route::get('/sitemaps/disciplines.xml', [SitemapController::class, 'disciplines'])->name('sitemap.disciplines');
Route::get('/sitemaps/providers.xml', [SitemapController::class, 'providers'])->name('sitemap.providers');
Route::get('/sitemaps/magazines.xml', [SitemapController::class, 'magazines'])->name('sitemap.magazines');

Route::get('/llms.txt', LlmsTxtController::class)->name('llms');
Route::get('/robots.txt', RobotsController::class)->name('robots');

// Public auth. Filament still owns /admin/login and /desk/login as
// internal panel infrastructure; these routes are the front-door
// nav uses. The name 'login' matches Laravel's default so the
// auth middleware's redirectTo() finds it without config changes.
Route::middleware('guest')->group(function (): void {
    Route::get('/login', Login::class)->name('login');
    Route::get('/register', Register::class)->name('register');
});

// Legacy signup bookmarks. The public site used to have three
// separate registration surfaces (shooter / director / supplier);
// they were folded into a single /register page with role tickboxes.
// Old inbound links keep working via permanent redirect.
Route::redirect('/directors/register', '/register', 301)->name('directors.register');
Route::redirect('/suppliers/register', '/register', 301)->name('suppliers.register');
Route::redirect('/desk/register', '/register', 301);

// Email verification. Fires for any user (shooter, MD, supplier) whose
// account was created via a Registered event — SendEmailVerificationNotification
// listener is bound in AppServiceProvider. Only the supplier onboarding
// route currently enforces `verified`; the notice page is the landing
// after signup and after the middleware rejects an unverified user.
Route::middleware('auth')->group(function (): void {
    Route::get('/email/verify', [EmailVerificationController::class, 'notice'])
        ->name('verification.notice');
    Route::get('/email/verify/{id}/{hash}', [EmailVerificationController::class, 'verify'])
        ->middleware(['signed', 'throttle:6,1'])
        ->name('verification.verify');
    Route::post('/email/verification-notification', [EmailVerificationController::class, 'resend'])
        ->middleware('throttle:6,1')
        ->name('verification.send');

    Route::get('/suppliers/start', function () {
        $user = auth()->user();

        if ($user instanceof User && $user->supplier_requested_at === null) {
            $user->forceFill(['supplier_requested_at' => now()])->save();
        }

        return redirect()->route('suppliers.onboard');
    })->name('suppliers.start');
});

// Supplier onboarding — the listing form itself. Requires a verified
// email so unclicked-inbox signups can't push "dealer" rows into the
// pending queue. Listings arrive with status=Pending; staff publish
// them from /admin. The thanks page renders the pending listing so
// the user has confirmation the submission landed.
Route::middleware(['auth', 'verified'])->group(function (): void {
    Route::get('/suppliers/onboard', SupplierCreateListing::class)->name('suppliers.onboard');
    Route::get('/suppliers/onboard/{provider:slug}/edit', SupplierEditListing::class)->name('suppliers.onboard.edit');
    Route::get('/suppliers/onboard/{provider:slug}/thanks', function (Provider $provider) {
        abort_unless($provider->claimed_by === auth()->id() || auth()->user()?->is_staff, 403);

        return view('public.suppliers.onboarded', ['provider' => $provider]);
    })->name('suppliers.onboard.thanks');
});

Route::post('/logout', function () {
    Auth::logout();
    session()->invalidate();
    session()->regenerateToken();

    return redirect('/');
})->middleware('auth')->name('logout');

// Paystack subscriptions. Callback is behind the app's normal session
// (so the flash message renders on redirect) but does not require
// auth — Paystack may return the customer on a fresh browser after
// completing checkout on their phone. Webhook is CSRF-exempted in
// bootstrap/app.php since Paystack cannot present a CSRF token.
Route::get('/upgrade', Upgrade::class)->middleware('auth')->name('upgrade');
Route::post('/upgrade/cancel', [PaystackController::class, 'cancel'])
    ->middleware('auth')
    ->name('upgrade.cancel');
Route::get('/paystack/callback', [PaystackController::class, 'callback'])->name('paystack.callback');
Route::post('/paystack/webhook', [PaystackController::class, 'webhook'])->name('paystack.webhook');

// Personal attendance log. Lives under /me/log so it sits next to
// the shooter home at /me. All routes are auth-gated; the export
// routes are additionally Pro-gated inside the controller so the UI
// can render an upgrade CTA instead of a 403 for Free users. The
// three GET URLs that users may have bookmarked at /my-log/* 301 to
// the new /me/log/* equivalents. Route names stay the same so every
// existing route() caller resolves to the new URL with no bounce.
Route::middleware('auth')->group(function (): void {
    Route::get('/me/log', [ShooterLogController::class, 'index'])->name('my-log');
    Route::delete('/me/log/{attendedEvent}', [ShooterLogController::class, 'destroy'])->name('my-log.destroy');
    Route::get('/me/log/export/csv', [ShooterLogController::class, 'exportCsv'])->name('my-log.export.csv');
    Route::get('/me/log/export/dedicated', [ShooterLogController::class, 'exportDedicated'])->name('my-log.export.dedicated');
    Route::get('/me/log/print', [ShooterLogController::class, 'printable'])->name('my-log.print');
    Route::get('/me/log/dedicated/print', [ShooterLogController::class, 'dedicatedPrintable'])->name('my-log.dedicated.print');
});
Route::redirect('/my-log', '/me/log', 301);
Route::redirect('/my-log/export/csv', '/me/log/export/csv', 301);
Route::redirect('/my-log/print', '/me/log/print', 301);

// Member self-service. Linked members see every ClubMember record
// where user_id matches or the email matches theirs; the Livewire
// components enforce the actual visibility and the payment paths
// (Paystack init + "I've paid by EFT" nudge) live on their own
// controller so the form submits are plain HTTP.
Route::middleware('auth')->group(function (): void {
    Route::get('/my-memberships', MyMemberships::class)->name('my-memberships');
    Route::get('/my-memberships/{member}', MyMembershipDetails::class)->name('my-memberships.show');
    Route::post('/my-memberships/invoice/{invoice}/paystack', [MembershipPaymentController::class, 'paystack'])
        ->name('my-memberships.paystack');
    Route::post('/my-memberships/invoice/{invoice}/notify-eft', [MembershipPaymentController::class, 'notifyEftPaid'])
        ->name('my-memberships.notify-eft');
});

// Email preferences. Two unauthenticated endpoints (one-click
// unsubscribe + resubscribe) that identify the user via a per-account
// token so they work from any inbox. One authenticated endpoint
// (/me/settings) for the full per-category preferences UI. The old
// /settings/notifications URL 301s to the new home; the route name
// stays the same so every existing route() caller resolves to the
// new URL with no bounce.
Route::get('/email/unsubscribe/{token}', [UnsubscribeController::class, 'show'])->name('email.unsubscribe');
Route::post('/email/unsubscribe/{token}', [UnsubscribeController::class, 'resubscribe'])->name('email.resubscribe');
Route::get('/me/settings', NotificationPreferences::class)
    ->middleware('auth')
    ->name('settings.notifications');
Route::get('/me/settings/dedicated-status', DedicatedStatus::class)
    ->middleware('auth')
    ->name('settings.dedicated-status');
Route::redirect('/settings/notifications', '/me/settings', 301);
// The old /me/settings/notifications URL (which some drafts of the
// spec and a few external emails referenced) 301s to the real page.
Route::redirect('/me/settings/notifications', '/me/settings', 301);

require __DIR__.'/mockups.php';
