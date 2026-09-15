<?php

use App\Http\Middleware\PreventClickjacking;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
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

        $middleware->web(append: [
            PreventClickjacking::class,
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
    })->create();
