<?php

namespace App\Providers;

use App\Models\Discipline;
use App\Models\Enquiry;
use App\Models\Event;
use App\Models\Organisation;
use App\Models\Provider;
use App\Models\User;
use App\Models\Venue;
use App\Policies\EventPolicy;
use App\Policies\OrganisationPolicy;
use App\Support\MailSettings;
use App\Support\PublicCache;
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
        //
    }

    public function boot(): void
    {
        // Behind Nginx Proxy Manager the app container only ever sees plain http
        // on port 80. If the public APP_URL is https, force every generated URL
        // (asset(), route(), Vite manifest URLs, Livewire endpoints) to use
        // https so browsers don't block them as mixed content.
        if (Str::startsWith((string) config('app.url'), 'https://')) {
            URL::forceScheme('https');
        }

        MailSettings::apply();

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

        $bumpPublic = static fn () => PublicCache::bump();

        foreach ([Event::class, Organisation::class, Venue::class, Provider::class, Discipline::class] as $model) {
            $model::saved($bumpPublic);
            $model::deleted($bumpPublic);
        }
    }
}
