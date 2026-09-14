<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class PreventClickjacking
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if ($request->routeIs('embed.calendar')) {
            $response->headers->remove('X-Frame-Options');
            $response->headers->set('Content-Security-Policy', 'frame-ancestors *');

            return $response;
        }

        $response->headers->set('X-Frame-Options', 'SAMEORIGIN');

        return $response;
    }
}
