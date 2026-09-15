<?php

use App\Enums\ProviderCategory;
use App\Enums\Province;
use App\Http\Controllers\CalendarController;
use App\Http\Controllers\DisciplineController;
use App\Http\Controllers\EmbedController;
use App\Http\Controllers\EnquiryController;
use App\Http\Controllers\EventController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\IcalController;
use App\Http\Controllers\LlmsTxtController;
use App\Http\Controllers\OrganisationController;
use App\Http\Controllers\ProviderController;
use App\Http\Controllers\ShooterCalendarController;
use App\Http\Controllers\SitemapController;
use App\Http\Controllers\StaticPageController;
use App\Http\Controllers\VenueController;
use Illuminate\Support\Facades\Route;

$provincePattern = implode('|', array_map(fn (Province $province) => $province->urlSlug(), Province::cases()));
$categoryPattern = implode('|', array_map(fn (ProviderCategory $category) => $category->urlSlug(), ProviderCategory::cases()));

Route::get('/', HomeController::class)->name('home');
Route::get('/calendar', CalendarController::class)->name('calendar');

Route::get('/disciplines', [DisciplineController::class, 'index'])->name('disciplines.index');
Route::get('/disciplines/{discipline:slug}/calendar.ics', [IcalController::class, 'discipline'])->name('ical.discipline');
Route::get('/disciplines/{discipline:slug}/{province}', [DisciplineController::class, 'show'])
    ->where('province', $provincePattern)
    ->name('disciplines.province');
Route::get('/disciplines/{discipline:slug}', [DisciplineController::class, 'show'])->name('disciplines.show');

Route::get('/clubs', [OrganisationController::class, 'index'])->name('clubs.index');
Route::get('/clubs/{organisation:slug}/calendar.ics', [IcalController::class, 'organisation'])->name('ical.organisation');
Route::get('/clubs/{organisation:slug}', [OrganisationController::class, 'show'])->name('clubs.show');
Route::get('/federations/{organisation:slug}', [OrganisationController::class, 'federation'])->name('federations.show');

Route::get('/ranges', [VenueController::class, 'index'])->name('ranges.index');
Route::get('/ranges/{venue:slug}', [VenueController::class, 'show'])->name('ranges.show');

Route::get('/suppliers', [ProviderController::class, 'index'])->name('suppliers.index');
Route::get('/suppliers/{category}/{province}', [ProviderController::class, 'category'])
    ->where(['category' => $categoryPattern, 'province' => $provincePattern])
    ->name('suppliers.province');
Route::get('/suppliers/{category}', [ProviderController::class, 'category'])
    ->where('category', $categoryPattern)
    ->name('suppliers.category');
Route::get('/supplier/{provider:slug}', [ProviderController::class, 'show'])->name('suppliers.show');

Route::get('/matches/{event:slug}', [EventController::class, 'show'])->name('matches.show');

Route::get('/my-calendar', [ShooterCalendarController::class, 'mine'])
    ->middleware('auth')
    ->name('my-calendar');
Route::get('/shooters/{shooter}/calendar.ics', [IcalController::class, 'shooter'])->name('ical.shooter');
Route::get('/shooters/{shooter}', [ShooterCalendarController::class, 'show'])->name('shooters.show');

Route::get('/embed/calendar', [EmbedController::class, 'calendar'])->name('embed.calendar');
Route::get('/embed/calendar.js', [EmbedController::class, 'script'])->name('embed.script');
Route::get('/embed', [StaticPageController::class, 'embedDocs'])->name('embed.docs');
Route::get('/oembed', [EmbedController::class, 'oembed'])->name('oembed');

Route::get('/advertise', [EnquiryController::class, 'advertise'])->name('advertise');
Route::get('/contact', [EnquiryController::class, 'create'])->name('contact');
Route::get('/enquire/{type}/{id}', [EnquiryController::class, 'listing'])
    ->where(['type' => 'organisation|provider|venue', 'id' => '[0-9]+'])
    ->name('enquiries.listing');
Route::post('/enquiries', [EnquiryController::class, 'store'])
    ->middleware('throttle:10,1')
    ->name('enquiries.store');
Route::get('/enquiries/thanks', [EnquiryController::class, 'thanks'])->name('enquiries.thanks');
Route::get('/claim', [StaticPageController::class, 'claim'])->name('claim');
Route::get('/privacy', [StaticPageController::class, 'privacy'])->name('privacy');

Route::get('/sitemap.xml', [SitemapController::class, 'index'])->name('sitemap');
Route::get('/sitemaps/pages.xml', [SitemapController::class, 'pages'])->name('sitemap.pages');
Route::get('/sitemaps/events.xml', [SitemapController::class, 'events'])->name('sitemap.events');
Route::get('/sitemaps/organisations.xml', [SitemapController::class, 'organisations'])->name('sitemap.organisations');
Route::get('/sitemaps/venues.xml', [SitemapController::class, 'venues'])->name('sitemap.venues');
Route::get('/sitemaps/disciplines.xml', [SitemapController::class, 'disciplines'])->name('sitemap.disciplines');
Route::get('/sitemaps/providers.xml', [SitemapController::class, 'providers'])->name('sitemap.providers');

Route::get('/llms.txt', LlmsTxtController::class)->name('llms');
