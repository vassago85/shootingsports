<?php

use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\CapabilitiesController;
use App\Http\Controllers\Api\V1\DeviceTokenController;
use App\Http\Controllers\Api\V1\DisciplineController;
use App\Http\Controllers\Api\V1\EventController;
use App\Http\Controllers\Api\V1\ManagedEnquiryController;
use App\Http\Controllers\Api\V1\ManagedEventController;
use App\Http\Controllers\Api\V1\ManagedPlacementController;
use App\Http\Controllers\Api\V1\MapController;
use App\Http\Controllers\Api\V1\MeController;
use App\Http\Controllers\Api\V1\SavedEventController;
use App\Http\Controllers\Api\V1\VenueController;
use Illuminate\Support\Facades\Route;

/*
 * Versioned mobile API. Discovery endpoints are public and match the
 * query string used by the web calendar so the same URL shape works
 * for both surfaces. Shooter endpoints require a Sanctum bearer
 * token issued by POST /api/v1/auth/login.
 *
 * Deliberately does NOT expose Filament desk/admin models. The web
 * Livewire and Filament panels stay on the session `web` guard and
 * are unaffected by this file.
 */
Route::prefix('v1')->name('api.v1.')->group(function (): void {
    // Auth surface. Login is throttled so credential stuffing cannot
    // simply grind against the endpoint.
    Route::post('auth/login', [AuthController::class, 'login'])
        ->middleware('throttle:5,1')
        ->name('auth.login');

    Route::post('auth/logout', [AuthController::class, 'logout'])
        ->middleware('auth:sanctum')
        ->name('auth.logout');

    // Public discovery.
    Route::get('events', [EventController::class, 'index'])->name('events.index');
    Route::get('events/{event:slug}', [EventController::class, 'show'])->name('events.show');
    Route::get('disciplines', [DisciplineController::class, 'index'])->name('disciplines.index');
    Route::get('venues', [VenueController::class, 'index'])->name('venues.index');
    Route::get('map', MapController::class)->name('map');

    // Shooter surface. Rate limit writes so a compromised token
    // cannot spam the pivot table.
    Route::middleware(['auth:sanctum', 'throttle:60,1'])->prefix('me')->name('me.')->group(function (): void {
        Route::get('/', [MeController::class, 'show'])->name('show');

        Route::get('saved-events', [SavedEventController::class, 'index'])->name('saved-events.index');
        Route::post('saved-events', [SavedEventController::class, 'store'])->name('saved-events.store');
        // Uses `{eventId}` (not `{event}`) so the app-wide slug binder in
        // AppServiceProvider does not intercept a numeric id. Mobile
        // clients receive event ids from the list resource; hitting
        // this route by slug is not supported.
        Route::delete('saved-events/{eventId}', [SavedEventController::class, 'destroy'])
            ->where('eventId', '[0-9]+')
            ->name('saved-events.destroy');

        Route::post('device-tokens', [DeviceTokenController::class, 'store'])->name('device-tokens.store');
        Route::delete('device-tokens', [DeviceTokenController::class, 'destroy'])->name('device-tokens.destroy');

        // Manage hub — the mobile Me tab reads capabilities from here
        // instead of re-deriving the clubs/shops/ranges list from a
        // dozen individual API calls.
        Route::get('capabilities', [CapabilitiesController::class, 'index'])->name('capabilities.index');

        // Light manage endpoints. Everything heavier (member register,
        // invoices, event wizard, placement edits, magazine issues)
        // stays on the desk website.
        Route::get('events', [ManagedEventController::class, 'index'])->name('events.index');
        Route::patch('events/{eventId}/status', [ManagedEventController::class, 'updateStatus'])
            ->where('eventId', '[0-9]+')
            ->name('events.status');

        Route::get('enquiries', [ManagedEnquiryController::class, 'index'])->name('enquiries.index');
        Route::post('enquiries/{enquiryId}/read', [ManagedEnquiryController::class, 'markRead'])
            ->where('enquiryId', '[0-9]+')
            ->name('enquiries.read');

        Route::get('placements', [ManagedPlacementController::class, 'index'])->name('placements.index');
    });
});
