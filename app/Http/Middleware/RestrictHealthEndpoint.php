<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RestrictHealthEndpoint
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->is('up')) {
            return $next($request);
        }

        if ($this->isAllowed($request)) {
            return $next($request);
        }

        abort(404);
    }

    protected function isAllowed(Request $request): bool
    {
        $ip = (string) $request->ip();

        if (in_array($ip, ['127.0.0.1', '::1'], true)) {
            return true;
        }

        return in_array($ip, (array) config('security.health_allowed_ips', []), true);
    }
}
