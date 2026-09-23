<?php

use App\Http\Controllers\MockupController;
use Illuminate\Support\Facades\Route;

/*
 * Redesign mockups. Isolated from production routes.
 * Nothing here writes to the database.
 */
Route::prefix('mockups')->name('mockups.')->group(function (): void {
    Route::get('/', [MockupController::class, 'index'])->name('index');
    Route::get('/home', [MockupController::class, 'home'])->name('home');
    Route::get('/matches', [MockupController::class, 'matches'])->name('matches');
    Route::get('/matches/calendar', [MockupController::class, 'calendar'])->name('matches.calendar');
    Route::get('/matches/map', [MockupController::class, 'map'])->name('matches.map');
    Route::get('/match/{slug?}', [MockupController::class, 'match'])->name('match');
    Route::get('/sports', [MockupController::class, 'sports'])->name('sports');
    Route::get('/sport/{slug?}', [MockupController::class, 'sport'])->name('sport');
    Route::get('/clubs', [MockupController::class, 'clubs'])->name('clubs');
    Route::get('/club/{slug?}', [MockupController::class, 'club'])->name('club');
    Route::get('/ranges', [MockupController::class, 'ranges'])->name('ranges');
    Route::get('/range/{slug?}', [MockupController::class, 'range'])->name('range');
    Route::get('/industry', [MockupController::class, 'industry'])->name('industry');
    Route::get('/business/{slug?}', [MockupController::class, 'business'])->name('business');
    Route::get('/search', [MockupController::class, 'search'])->name('search');

    Route::prefix('v2')->name('v2.')->group(function (): void {
        Route::get('/', [MockupController::class, 'home'])->name('home');
        Route::get('/matches', [MockupController::class, 'matches'])->name('matches');
        Route::get('/matches/calendar', [MockupController::class, 'calendar'])->name('matches.calendar');
        Route::get('/matches/map', [MockupController::class, 'map'])->name('matches.map');
        Route::get('/match/{slug?}', [MockupController::class, 'match'])->name('match');
        Route::get('/sports', [MockupController::class, 'sports'])->name('sports');
        Route::get('/sport/{slug?}', [MockupController::class, 'sport'])->name('sport');
        Route::get('/clubs', [MockupController::class, 'clubs'])->name('clubs');
        Route::get('/club/{slug?}', [MockupController::class, 'club'])->name('club');
        Route::get('/ranges', [MockupController::class, 'ranges'])->name('ranges');
        Route::get('/range/{slug?}', [MockupController::class, 'range'])->name('range');
        Route::get('/industry', [MockupController::class, 'industry'])->name('industry');
        Route::get('/business/{slug?}', [MockupController::class, 'business'])->name('business');
        Route::get('/search', [MockupController::class, 'search'])->name('search');
    });
    Route::get('/account', [MockupController::class, 'account'])->name('account');
    Route::get('/account/following', [MockupController::class, 'following'])->name('account.following');
    Route::get('/onboarding', [MockupController::class, 'onboarding'])->name('onboarding');
    Route::get('/apps', [MockupController::class, 'apps'])->name('apps');

    Route::prefix('manage')->name('manage.')->group(function (): void {
        Route::get('/', [MockupController::class, 'manage'])->name('index');
        Route::get('/matches', [MockupController::class, 'manageMatches'])->name('matches');
        Route::get('/matches/new', [MockupController::class, 'manageMatchesNew'])->name('matches.new');
        Route::get('/profile', [MockupController::class, 'manageProfilePage'])->name('profile');
    });

    Route::prefix('admin')->name('admin.')->group(function (): void {
        Route::redirect('/', '/mockups/admin/dashboard');
        Route::get('/dashboard', [MockupController::class, 'adminDashboard'])->name('dashboard');
        Route::get('/matches', [MockupController::class, 'adminMatches'])->name('matches');
        Route::get('/matches/edit/{slug?}', [MockupController::class, 'adminMatchEdit'])->name('matches.edit');
        Route::get('/clubs', [MockupController::class, 'adminClubs'])->name('clubs');
        Route::get('/ranges', [MockupController::class, 'adminRanges'])->name('ranges');
        Route::get('/sports', [MockupController::class, 'adminSports'])->name('sports');
        Route::get('/industry', [MockupController::class, 'adminIndustry'])->name('industry');
        Route::get('/submissions', [MockupController::class, 'adminSubmissions'])->name('submissions');
        Route::get('/quality', [MockupController::class, 'adminQuality'])->name('quality');
        Route::get('/duplicates', [MockupController::class, 'adminDuplicates'])->name('duplicates');
        Route::get('/reports', [MockupController::class, 'adminReports'])->name('reports');
        Route::get('/advertising', [MockupController::class, 'adminAdvertising'])->name('advertising');
    });
});
