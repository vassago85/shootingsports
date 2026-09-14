<?php

namespace App\Providers;

use App\Models\Discipline;
use App\Models\Event;
use App\Models\Organisation;
use App\Models\Provider;
use App\Models\User;
use App\Models\Venue;
use App\Policies\EventPolicy;
use App\Support\PublicCache;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        Relation::enforceMorphMap([
            'organisation' => Organisation::class,
            'venue' => Venue::class,
            'event' => Event::class,
            'provider' => Provider::class,
            'user' => User::class,
        ]);

        Gate::policy(Event::class, EventPolicy::class);

        $bumpPublic = static fn () => PublicCache::bump();

        foreach ([Event::class, Organisation::class, Venue::class, Provider::class, Discipline::class] as $model) {
            $model::saved($bumpPublic);
            $model::deleted($bumpPublic);
        }
    }
}
