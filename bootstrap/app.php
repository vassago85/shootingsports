<?php

use App\Http\Middleware\ApplySecurityHeaders;
use App\Http\Middleware\CanonicalHost;
use App\Http\Middleware\EnsureComingSoonAccess;
use App\Http\Middleware\RedirectLegacyWordPressUrls;
use App\Http\Middleware\RedirectRetiredAdminUrls;
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

        // Old WordPress URLs (sample-page, wp-admin, /?p=…, /category/*,
        // /tag/*) are not matched by any Laravel route, so the `web`
        // group never runs on them. Register globally so the 301/410
        // responses fire before the router can emit a plain 404.
        $middleware->append(RedirectLegacyWordPressUrls::class);

        // Lets the /api/v1 routes read the signed-in web session cookie
        // (via Sanctum's EnsureFrontendRequestsAreStateful) so a user
        // logged into the website can call /api/v1/me from the browser
        // without issuing a bearer token. Mobile clients still auth
        // with Sanctum bearer tokens from /api/v1/auth/login — this
        // only adds the SPA-session path, it doesn't remove the token
        // path. SANCTUM_STATEFUL_DOMAINS controls which hosts count.
        $middleware->statefulApi();

        $middleware->web(append: [
            CanonicalHost::class,
            EnsureComingSoonAccess::class,
            RedirectRetiredAdminUrls::class,
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
