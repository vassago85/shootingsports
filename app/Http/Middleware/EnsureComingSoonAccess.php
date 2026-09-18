<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

/**
 * Public-site gate. When `config('coming-soon.enabled')` is true every
 * request routed through the web middleware stack is redirected to a
 * branded landing page unless the path is allowlisted or the user is
 * a "builder" — staff or a match director.
 *
 * Filament panels (/admin, /desk) have their own middleware group and
 * are never touched by this class, so admins can keep working through
 * the gate without any special allowlist entries here.
 */
class EnsureComingSoonAccess
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! config('coming-soon.enabled')) {
            return $next($request);
        }

        if ($this->isAllowlisted($request)) {
            return $next($request);
        }

        if (config('coming-soon.expose_public') && $this->isPublicRoute($request)) {
            return $next($request);
        }

        if ($this->userHasBuilderAccess()) {
            return $next($request);
        }

        return redirect()->route('coming-soon');
    }

    protected function isAllowlisted(Request $request): bool
    {
        foreach ((array) config('coming-soon.allowlist', []) as $pattern) {
            if ($request->is($pattern)) {
                return true;
            }
        }

        return false;
    }

    protected function isPublicRoute(Request $request): bool
    {
        $name = $request->route()?->getName();

        if (! is_string($name) || $name === '') {
            return false;
        }

        foreach ((array) config('coming-soon.public_routes', []) as $pattern) {
            if (Str::is($pattern, $name)) {
                return true;
            }
        }

        return false;
    }

    protected function userHasBuilderAccess(): bool
    {
        $user = Auth::user();

        if ($user === null) {
            return false;
        }

        return (bool) ($user->is_staff || $user->is_match_director);
    }
}
