<?php

use App\Http\Controllers\CalendarController;
use App\Http\Controllers\DisciplineController;
use App\Http\Controllers\EmbedController;
use App\Http\Controllers\EnquiryController;
use App\Http\Controllers\EventController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\IcalController;
use App\Http\Controllers\OrganisationController;
use App\Http\Controllers\ProviderController;
use App\Http\Controllers\SitemapController;
use App\Http\Controllers\StaticPageController;
use App\Http\Controllers\VenueController;
use App\Enums\ProviderCategory;
use App\Enums\Province;
use App\Models\Discipline;
use App\Models\Event;
use App\Models\Organisation;
use App\Models\Provider;
use App\Models\Venue;
use Illuminate\Support\Facades\Route;

$provincePattern = implode('|', array_map(fn (Province $province) => $province->urlSlug(), Province::cases()));
$categoryPattern = implode('|', array_map(fn (ProviderCategory $category) => $category->urlSlug(), ProviderCategory::cases()));

Route::bind('discipline', function (string $value): Discipline {
    return Discipline::query()->where('slug', $value)->where('is_published', true)->firstOrFail();
});

Route::bind('event', function (string $value): Event {
    return Event::query()->where('slug', $value)->firstOrFail();
});

Route::bind('organisation', function (string $value): Organisation {
    return Organisation::query()->where('slug', $value)->firstOrFail();
});

Route::bind('venue', function (string $value): Venue {
    return Venue::query()->where('slug', $value)->firstOrFail();
});

Route::bind('provider', function (string $value): Provider {
    return Provider::query()->where('slug', $value)->firstOrFail();
});

Route::get('/', HomeController::class)->name('home');
Route::get('/calendar', CalendarController::class)->name('calendar');

Route::get('/disciplines', [DisciplineController::class, 'index'])->name('disciplines.index');
Route::get('/disciplines/{discipline}/calendar.ics', [IcalController::class, 'discipline'])->name('ical.discipline');
Route::get('/disciplines/{discipline}/{province}', [DisciplineController::class, 'show'])
    ->where('province', $provincePattern)
    ->name('disciplines.province');
Route::get('/disciplines/{discipline}', [DisciplineController::class, 'show'])->name('disciplines.show');

Route::get('/clubs', [OrganisationController::class, 'index'])->name('clubs.index');
Route::get('/clubs/{organisation}/calendar.ics', [IcalController::class, 'organisation'])->name('ical.organisation');
Route::get('/clubs/{organisation}', [OrganisationController::class, 'show'])->name('clubs.show');
Route::get('/federations/{organisation}', [OrganisationController::class, 'federation'])->name('federations.show');

Route::get('/ranges', [VenueController::class, 'index'])->name('ranges.index');
Route::get('/ranges/{venue}', [VenueController::class, 'show'])->name('ranges.show');

Route::get('/suppliers', [ProviderController::class, 'index'])->name('suppliers.index');
Route::get('/suppliers/{category}/{province}', [ProviderController::class, 'category'])
    ->where(['category' => $categoryPattern, 'province' => $provincePattern])
    ->name('suppliers.province');
Route::get('/suppliers/{category}', [ProviderController::class, 'category'])
    ->where('category', $categoryPattern)
    ->name('suppliers.category');
Route::get('/supplier/{provider}', [ProviderController::class, 'show'])->name('suppliers.show');

Route::get('/matches/{event}', [EventController::class, 'show'])->name('matches.show');

Route::get('/embed/calendar', [EmbedController::class, 'calendar'])->name('embed.calendar');
Route::get('/embed/calendar.js', [EmbedController::class, 'script'])->name('embed.script');
Route::get('/embed', [StaticPageController::class, 'embedDocs'])->name('embed.docs');

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
Route::get('/sitemaps/events.xml', [SitemapController::class, 'events'])->name('sitemap.events');
Route::get('/sitemaps/organisations.xml', [SitemapController::class, 'organisations'])->name('sitemap.organisations');
Route::get('/sitemaps/venues.xml', [SitemapController::class, 'venues'])->name('sitemap.venues');
Route::get('/sitemaps/disciplines.xml', [SitemapController::class, 'disciplines'])->name('sitemap.disciplines');
Route::get('/sitemaps/providers.xml', [SitemapController::class, 'providers'])->name('sitemap.providers');
