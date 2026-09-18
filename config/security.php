<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Health endpoint (/up)
    |--------------------------------------------------------------------------
    |
    | Comma-separated IPs allowed to hit /up in production. Loopback is
    | always allowed so the box can probe itself. Everyone else gets 404.
    |
    */

    'health_allowed_ips' => array_values(array_filter(array_map(
        trim(...),
        explode(',', (string) env('HEALTH_ALLOWED_IPS', '')),
    ))),

    /*
    |--------------------------------------------------------------------------
    | HSTS
    |--------------------------------------------------------------------------
    |
    | Only attached when the request is already HTTPS (including when
    | X-Forwarded-Proto is https behind Nginx Proxy Manager). No preload
    | until every subdomain is ready for HTTPS-only.
    |
    */

    'hsts_max_age' => 31536000,

    'hsts_include_subdomains' => true,

    /*
    |--------------------------------------------------------------------------
    | Permissions-Policy
    |--------------------------------------------------------------------------
    */

    'permissions_policy' => 'geolocation=(), camera=(), microphone=(), payment=()',

    /*
    |--------------------------------------------------------------------------
    | CSP (Report-Only)
    |--------------------------------------------------------------------------
    |
    | Enforcing CSP stays frame-ancestors only (see ApplySecurityHeaders +
    | nginx). This report-only policy is wider so we can watch Livewire,
    | Filament, Turnstile, fonts, Umami and Paystack before flipping it.
    |
    */

    'csp_report_only' => implode(' ', [
        "default-src 'self';",
        "script-src 'self' 'unsafe-inline' https://challenges.cloudflare.com https://js.paystack.co https://analytics.charsley.co.za https://analytics.charsleydigital.co.za;",
        "style-src 'self' 'unsafe-inline' https://fonts.googleapis.com;",
        "font-src 'self' https://fonts.gstatic.com data:;",
        "img-src 'self' data: blob: https:;",
        "connect-src 'self' https://challenges.cloudflare.com https://api.paystack.co https://analytics.charsley.co.za https://analytics.charsleydigital.co.za https://nominatim.openstreetmap.org https://*.cartocdn.com;",
        'frame-src https://challenges.cloudflare.com https://js.paystack.co;',
        "frame-ancestors 'self';",
        "base-uri 'self';",
        "form-action 'self';",
    ]),

];
