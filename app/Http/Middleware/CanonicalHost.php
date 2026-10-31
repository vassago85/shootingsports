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
        // Only upgrade schemes. Never redirect an https request down to
        // http, even when a dev APP_URL is still http — that would blow
        // HSTS away and send the browser back out through the plaintext
        // round-trip we were trying to collapse.
        $needsSchemeFix = $scheme === 'https' && $requestScheme === 'http';

        if (! $needsHostFix && ! $needsSlashFix && ! $needsSchemeFix) {
            return $next($request);
        }

        $targetPath = $needsSlashFix ? rtrim($path, '/') : $path;

        if ($targetPath === '') {
            $targetPath = '/';
        }

        $query = $request->getQueryString();
        $target = $scheme.'://'.$canonicalHost.$targetPath
            .($query ? '?'.$query : '');

        return redirect()->away($target, 301);
    }
}
