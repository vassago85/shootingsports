<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Resend, Postmark, AWS, and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'mailgun' => [
        'domain' => env('MAILGUN_DOMAIN'),
        'secret' => env('MAILGUN_SECRET'),
        'endpoint' => env('MAILGUN_ENDPOINT', 'api.eu.mailgun.net'),
        'scheme' => 'https',
    ],

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    // Search Console verification (fallback). Prefer the static
    // public/google*.html file — this meta tag is a belt-and-braces
    // secondary handshake for the same property.
    'google' => [
        'site_verification' => env('GOOGLE_SITE_VERIFICATION'),
    ],

    // Shared Umami analytics (self-hosted, first-party). Both values must
    // be set for the script tag to render; leaving either blank keeps the
    // public layout tracker-free for local dev and previews.
    'umami' => [
        'script_url' => env('UMAMI_SCRIPT_URL'),
        'website_id' => env('UMAMI_WEBSITE_ID'),
    ],

    // Optional CARTO basemap key for /map. When blank, the map falls
    // back to free OSM tiles with a CSS mute — CARTO watermarks
    // "API KEY REQUIRED" without a key.
    'carto' => [
        'api_key' => env('CARTO_API_KEY'),
    ],

    // Paystack subscriptions. Test keys start pk_test_ / sk_test_ and are
    // safe to commit around (they only touch the sandbox). Live keys start
    // pk_live_ / sk_live_ and are set on the server only, never here.
    //
    // Plan codes are created once via `php artisan paystack:setup-plans`
    // (idempotent) and pasted back into the server .env. Leaving them
    // blank makes the UpgradePrompt fall back to the waitlist-only mode.
    'paystack' => [
        'public_key' => env('PAYSTACK_PUBLIC_KEY'),
        'secret_key' => env('PAYSTACK_SECRET_KEY'),
        'base_url' => env('PAYSTACK_BASE_URL', 'https://api.paystack.co'),
        'currency' => env('PAYSTACK_CURRENCY', 'ZAR'),
        'plan_codes' => [
            'annual' => env('PAYSTACK_PLAN_ANNUAL'),
            'monthly' => env('PAYSTACK_PLAN_MONTHLY'),
        ],
    ],

];
