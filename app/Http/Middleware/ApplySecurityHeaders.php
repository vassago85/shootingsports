<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ApplySecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $isEmbed = $request->routeIs('embed.calendar');

        if ($isEmbed) {
            $response->headers->remove('X-Frame-Options');
            $response->headers->set('Content-Security-Policy', 'frame-ancestors *');
        } else {
            $response->headers->set('X-Frame-Options', 'SAMEORIGIN');
        }

        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $response->headers->set(
            'Permissions-Policy',
            (string) config('security.permissions_policy'),
        );

        $reportOnly = (string) config('security.csp_report_only');
        if ($isEmbed) {
            $reportOnly = str_replace(
                "frame-ancestors 'self';",
                'frame-ancestors *;',
                $reportOnly,
            );
        }
        $response->headers->set('Content-Security-Policy-Report-Only', $reportOnly);

        if ($request->secure()) {
            $maxAge = (int) config('security.hsts_max_age');
            $hsts = 'max-age='.$maxAge;
            if (config('security.hsts_include_subdomains')) {
                $hsts .= '; includeSubDomains';
            }
            $response->headers->set('Strict-Transport-Security', $hsts);
        }

        return $response;
    }
}
