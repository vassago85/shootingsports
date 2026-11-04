<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * SEO: collapse duplicate hosts, plain http, and trailing slashes onto APP_URL.
 * Edge redirects (Nginx / Cloudflare) should still do www and http; this
 * is the in-app safety net so canonicals never self-vouch for www or http.
 */
class CanonicalHost
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! in_array($request->method(), ['GET', 'HEAD'], true)) {
            return $next($request);
        }

        $appUrl = (string) config('app.url');
        $canonical = parse_url($appUrl);

        if (! is_array($canonical) || empty($canonical['host'])) {
            return $next($request);
        }

        $canonicalHost = strtolower((string) $canonical['host']);
        $requestHost = strtolower($request->getHost());
        $canonicalApex = preg_replace('/^www\./', '', $canonicalHost) ?? $canonicalHost;
        $requestApex = preg_replace('/^www\./', '', $requestHost) ?? $requestHost;

        // Leave local/preview hosts alone (Laragon, IP, etc.).
        if ($requestApex !== $canonicalApex) {
            return $next($request);
        }

        $path = $request->getPathInfo();
        $scheme = strtolower((string) ($canonical['scheme'] ?? 'https'));
        $requestScheme = strtolower($request->getScheme());
        $needsHostFix = $requestHost !== $canonicalHost;
        $needsSlashFix = $path !== '/' && str_ends_with($path, '/');
        $lowercased = self::lowercasePath($path);
        $needsCaseFix = $lowercased !== $path;
        // Only upgrade schemes. Never redirect an https request down to
        // http, even when a dev APP_URL is still http — that would blow
        // HSTS away and send the browser back out through the plaintext
        // round-trip we were trying to collapse.
        $needsSchemeFix = $scheme === 'https' && $requestScheme === 'http';

        if (! $needsHostFix && ! $needsSlashFix && ! $needsSchemeFix && ! $needsCaseFix) {
            return $next($request);
        }

        $targetPath = $needsSlashFix ? rtrim($path, '/') : $path;
        $targetPath = self::lowercasePath($targetPath);

        if ($targetPath === '') {
            $targetPath = '/';
        }

        $query = $request->getQueryString();
        $target = $scheme.'://'.$canonicalHost.$targetPath
            .($query ? '?'.$query : '');

        return redirect()->away($target, 301);
    }

    /**
     * Fold the path to lowercase for public content routes only.
     *
     * Opaque tokens (email confirms, enquiry thread tokens, Paystack
     * callbacks) are case-sensitive and must survive untouched, so
     * only paths that start with a known lowercase-slug prefix are
     * rewritten. Slugs in this app are all lowercase kebab-case, so
     * `/Events/Some-Slug` is always a typo or a scraper re-casing a
     * URL — a 301 to `/events/some-slug` collapses the pair.
     */
    private static function lowercasePath(string $path): string
    {
        if ($path === '/' || $path === '') {
            return $path;
        }

        $prefixes = [
            '/events/',
            '/clubs/',
            '/ranges/',
            '/disciplines/',
            '/divisions/',
            '/suppliers/',
            '/supplier/',
            '/federations/',
            '/magazines/',
            '/shooters/',
        ];

        foreach ($prefixes as $prefix) {
            if (str_starts_with(strtolower($path), $prefix)) {
                return strtolower($path);
            }
        }

        // Single-segment index pages — safe to lowercase in full.
        $roots = [
            '/calendar', '/calendar/month', '/map', '/find', '/feed',
            '/claim', '/privacy', '/terms', '/advertise', '/contact',
            '/clubs', '/ranges', '/suppliers', '/disciplines',
            '/this-weekend', '/magazines',
        ];

        if (in_array(strtolower($path), $roots, true)) {
            return strtolower($path);
        }

        return $path;
    }
}
