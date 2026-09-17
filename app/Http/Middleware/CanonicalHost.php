<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * SEO: collapse duplicate hosts and trailing-slash URLs onto APP_URL.
 * Edge redirects (Nginx / Cloudflare) should still do www→apex; this
 * is the in-app safety net so canonicals never self-vouch for www.
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
        $needsHostFix = $requestHost !== $canonicalHost;
        $needsSlashFix = $path !== '/' && str_ends_with($path, '/');

        if (! $needsHostFix && ! $needsSlashFix) {
            return $next($request);
        }

        $scheme = $canonical['scheme'] ?? 'https';
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
