<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Phase 2 nav restructure — a handful of admin URLs moved into
 * tabs or header actions on other pages. Rather than kill the old
 * Filament pages (and lose the detection logic that lives in them),
 * we intercept the retired URLs here and 301 them to their new home.
 *
 * This runs before Filament resolves its own routes because it is
 * registered as a global `web` middleware in `bootstrap/app.php`.
 */
class RedirectRetiredAdminUrls
{
    /**
     * @var array<string, string>
     */
    private const REDIRECTS = [
        'admin/verification-dashboard' => '/admin/organisations',
        'admin/venue-duplicates' => '/admin/venues',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        if ($request->isMethod('GET') || $request->isMethod('HEAD')) {
            $path = trim($request->path(), '/');

            if (isset(self::REDIRECTS[$path])) {
                return redirect(self::REDIRECTS[$path], 301);
            }
        }

        return $next($request);
    }
}
