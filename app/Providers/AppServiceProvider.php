<?php

namespace App\Providers;

use App\Models\Discipline;
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

        Relation::enforceMorphMap([
            'organisation' => Organisation::class,
            'venue' => Venue::class,
            'event' => Event::class,
            'provider' => Provider::class,
            'user' => User::class,
            'enquiry' => \App\Models\Enquiry::class,
        ]);

        Gate::policy(Event::class, EventPolicy::class);
        Gate::policy(Organisation::class, OrganisationPolicy::class);

        $bumpPublic = static fn () => PublicCache::bump();

        foreach ([Event::class, Organisation::class, Venue::class, Provider::class, Discipline::class] as $model) {
            $model::saved($bumpPublic);
            $model::deleted($bumpPublic);
        }
    }
}
