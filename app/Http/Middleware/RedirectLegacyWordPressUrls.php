<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * The previous shootingsports.co.za was a WordPress site. Its URLs
 * are still indexed by search engines, so inbound links from the open
 * web keep landing on paths that mean nothing to the Laravel app.
 * Two shapes:
 *
 * - A short map of named pages that have a direct equivalent today
 *   gets a 301 to the current URL (sample-page → home, legacy
 *   registration / members-portal → the current login + register
 *   routes).
 * - A set of WordPress-only paths (wp-admin, wp-login.php, numeric
 *   /?p=123 permalinks, /category/* and /tag/* taxonomy archives)
 *   has no equivalent on this site and returns 410 Gone so crawlers
 *   drop them from the index instead of retrying forever.
 *
 * Trailing and non-trailing slashes resolve to the same entry: the
 * match is on `trim($path, '/')` so '/sample-page' and
 * '/sample-page/' are the same key.
 *
 * Registered in bootstrap/app.php under the global `web` middleware
 * group so it runs before route matching — the 410s and 301s fire
 * before Laravel can emit a plain 404.
 */
class RedirectLegacyWordPressUrls
{
    /**
     * Fixed-path 301 map. Values are route names so a future URL
     * change on the live route is picked up automatically.
     *
     * @var array<string, string>
     */
    private const REDIRECTS = [
        'sample-page' => 'home',
        'new-user-register' => 'register',
        'members-portal' => 'login',
    ];

    /**
     * Exact-path 410 list.
     *
     * @var array<int, string>
     */
    private const GONE_PATHS = [
        'wp-login.php',
    ];

    /**
     * First-segment 410 list. Any path whose first `/`-separated
     * segment matches returns 410 — covers /wp-admin, /wp-admin/anything,
     * /category/anything, /tag/anything.
     *
     * @var array<int, string>
     */
    private const GONE_PREFIXES = [
        'wp-admin',
        'category',
        'tag',
    ];

    /**
     * WordPress permalink query keys that only ever appeared on `/`
     * on the old site (plain ?p=123 and ?page_id=12 permalinks).
     *
     * @var array<int, string>
     */
    private const WP_ROOT_QUERY_KEYS = ['p', 'page_id'];

    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->isMethod('GET') && ! $request->isMethod('HEAD')) {
            return $next($request);
        }

        $path = trim($request->path(), '/');

        if ($path === '' && $this->hasWordPressRootQuery($request)) {
            return $this->gone();
        }

        if (array_key_exists($path, self::REDIRECTS)) {
            return redirect()->route(self::REDIRECTS[$path], status: 301);
        }

        if (in_array($path, self::GONE_PATHS, true)) {
            return $this->gone();
        }

        $firstSegment = $path === '' ? '' : explode('/', $path, 2)[0];

        if ($firstSegment !== '' && in_array($firstSegment, self::GONE_PREFIXES, true)) {
            return $this->gone();
        }

        return $next($request);
    }

    private function hasWordPressRootQuery(Request $request): bool
    {
        foreach (self::WP_ROOT_QUERY_KEYS as $key) {
            if ($request->query($key) !== null) {
                return true;
            }
        }

        return false;
    }

    private function gone(): Response
    {
        return response('Gone', 410)
            ->header('Content-Type', 'text/plain; charset=utf-8')
            ->header('X-Robots-Tag', 'noindex');
    }
}
