<?php

namespace App\Providers;

use App\Http\Responses\LogoutResponse as PublicHomeLogoutResponse;
use App\Models\Discipline;
use App\Models\Enquiry;
use App\Models\Event;
use App\Models\Organisation;
use App\Models\Provider;
use App\Models\User;
use App\Models\Venue;
use App\Policies\EventPolicy;
use App\Policies\OrganisationPolicy;
use App\Services\Geocoding\Geocoder;
use App\Services\Geocoding\NominatimGeocoder;
use App\Support\MailSettings;
use App\Support\PublicCache;
use App\Support\TurnstileSettings;
use Filament\Auth\Http\Responses\Contracts\LogoutResponse as FilamentLogoutResponse;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Filament (both /admin and /desk) ships you back to the panel
        // login screen after signing out — reads as "the site kicked
        // me out". Override the contract to redirect to the public
        // home page instead. See App\Http\Responses\LogoutResponse.
        //
        // Registered via ->booted() (not directly) because each
        // Filament PanelProvider::packageBooted() calls scoped() on the
        // same contract, which runs AFTER AppServiceProvider::register.
        // A booted() callback runs after every provider has booted, so
        // our bind is the final one and wins for both panels.
        $this->app->booted(function (): void {
            $this->app->bind(FilamentLogoutResponse::class, PublicHomeLogoutResponse::class);
        });

        $this->app->bind(
            Geocoder::class,
            NominatimGeocoder::class,
        );
    }

    public function boot(): void
    {
        // Canonical public host from APP_URL so route()/url()/canonical
        // tags never follow a www request host (duplicate-index risk).
        $appUrl = rtrim((string) config('app.url'), '/');

        if ($appUrl !== '') {
            URL::forceRootUrl($appUrl);
        }

        if (Str::startsWith($appUrl, 'https://')) {
            URL::forceScheme('https');
        }

        MailSettings::apply();
        TurnstileSettings::apply();

        // Binders live here, not in routes/web.php: `route:cache` (used in
        // the Docker entrypoint) never loads web.php, so slug lookups would
        // fall back to implicit id binding and Postgres 500s on the slug.
        Route::bind('discipline', fn (string $value): Discipline => Discipline::query()
            ->where('slug', $value)
            ->where('is_published', true)
            ->firstOrFail());
        Route::bind('event', fn (string $value): Event => Event::query()
            ->where('slug', $value)
            ->firstOrFail());
        Route::bind('organisation', fn (string $value): Organisation => Organisation::query()
            ->where('slug', $value)
            ->firstOrFail());
        Route::bind('venue', fn (string $value): Venue => Venue::query()
            ->where('slug', $value)
            ->firstOrFail());
        Route::bind('provider', fn (string $value): Provider => Provider::query()
            ->where('slug', $value)
            ->firstOrFail());

        Relation::enforceMorphMap([
            'organisation' => Organisation::class,
            'venue' => Venue::class,
            'event' => Event::class,
            'provider' => Provider::class,
            'user' => User::class,
            'enquiry' => Enquiry::class,
        ]);

        Gate::policy(Event::class, EventPolicy::class);
        Gate::policy(Organisation::class, OrganisationPolicy::class);

        // Freemium gates. Every plan check in the codebase must resolve
        // through one of these — never compare $user->plan strings
        // inline in Livewire, Blade or controllers. Guest users are
        // always false (the ?User signature returns null on guests, so
        // the null-coalesce below trips false).
        Gate::define('pro', fn (?User $user): bool => (bool) $user?->isPro());
        Gate::define('export-season', fn (?User $user): bool => (bool) $user?->allows('export'));
        Gate::define('unlimited-follows', fn (?User $user): bool => $user !== null && $user->limit('follows') === null);
        Gate::define('full-history', fn (?User $user): bool => $user !== null && $user->limit('history_months') === null);
        // Attendance log — logging up to the free cap is open to any
        // signed-in user; the annual export PDF/CSV is Pro-only. Kept
        // as two gates so views can hide UI cleanly without repeating
        // the plan lookup.
        Gate::define('log-attendance', fn (?User $user): bool => $user !== null);
        Gate::define('export-attendance-log', fn (?User $user): bool => (bool) $user?->allows('export'));

        $bumpPublic = static fn () => PublicCache::bump();

        foreach ([Event::class, Organisation::class, Venue::class, Provider::class, Discipline::class] as $model) {
            $model::saved($bumpPublic);
            $model::deleted($bumpPublic);
        }
    }
}
