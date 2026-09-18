<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Coming Soon Gate
    |--------------------------------------------------------------------------
    |
    | When enabled, every request routed through the `web` middleware stack
    | is intercepted and redirected to a branded "coming soon" landing
    | page. Staff and match directors bypass the gate so the team can
    | keep building; everyone else — guests and plain shooters — see
    | the landing page. Filament panels (/admin, /desk) run on their
    | own middleware stack and are unaffected by this flag.
    |
    */

    'enabled' => (bool) env('COMING_SOON', false),

    /*
    |--------------------------------------------------------------------------
    | Allowlist
    |--------------------------------------------------------------------------
    |
    | Path patterns (Request::is()-style, no leading slash, `*` wildcard)
    | that pass through even when the gate is on. Login/logout must stay
    | open so staff/MDs can reach the site; `livewire/*` is needed for
    | the login form's Livewire posts. Livewire 4 hashes that path from
    | APP_KEY (`livewire-{hash}/update`), so `livewire/*` alone misses it
    | and the login POST is bounced to coming-soon before credentials
    | checked. Sitemap XML and llms.txt stay open so crawlers do not
    | receive the coming-soon HTML page. Paystack webhooks and email
    | unsubscribe links must keep working for existing users.
    |
    */

    'allowlist' => [
        'coming-soon',
        'coming-soon/interest',
        'coming-soon/confirm/*',
        'login',
        'logout',
        'up',
        'livewire/*',
        'livewire-*/*',
        'sitemap.xml',
        'sitemaps/*',
        'llms.txt',
        'robots.txt',
        '.well-known/*',
        'paystack/*',
        'email/unsubscribe/*',
    ],

];
