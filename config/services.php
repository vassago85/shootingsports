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

];
