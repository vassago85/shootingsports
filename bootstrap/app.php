<?php

use App\Http\Middleware\ApplySecurityHeaders;
use App\Http\Middleware\CanonicalHost;
use App\Http\Middleware\EnsureComingSoonAccess;
use App\Http\Middleware\RestrictHealthEndpoint;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Routing\Exceptions\InvalidSignatureException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Trust the Docker/Nginx Proxy Manager reverse proxy in front of us so
        // Laravel honours X-Forwarded-Proto and renders https:// asset URLs.
        $middleware->trustProxies(at: '*', headers: Request::HEADER_X_FORWARDED_FOR
            | Request::HEADER_X_FORWARDED_HOST
            | Request::HEADER_X_FORWARDED_PORT
            | Request::HEADER_X_FORWARDED_PROTO
            | Request::HEADER_X_FORWARDED_AWS_ELB);

        // Unified public login. Filament panels still redirect to their own
        // /admin/login and /desk/login internally; this is the fallback
        // for auth-middlewared app routes (like /my-calendar).
        $middleware->redirectGuestsTo(fn () => route('login'));

        // /up is registered without the web stack, so this must be global.
        $middleware->append(RestrictHealthEndpoint::class);

        $middleware->web(append: [
            CanonicalHost::class,
            EnsureComingSoonAccess::class,
            ApplySecurityHeaders::class,
        ]);

        // Paystack posts webhooks from their own IPs — no CSRF token
        // is available. Signature verification happens inside the
        // controller against the raw body.
        $middleware->validateCsrfTokens(except: [
            'paystack/webhook',
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );

        // A confirmation link is only valid for an hour. Once it expires
        // the signed middleware would otherwise show a bare 403, with no
        // way to ask for a fresh email. Send them back to the notice page
        // where they can request another.
        $exceptions->render(function (InvalidSignatureException $exception, Request $request) {
            if (! $request->routeIs('verification.verify')) {
                return null;
            }

            return redirect()
                ->route('verification.notice')
                ->with('status', 'verification-link-expired');
        });
    })->create();
