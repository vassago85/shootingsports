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
    | the login form's Livewire posts; Paystack webhooks and email
    | unsubscribe links must keep working for existing users.
    |
    */

    'allowlist' => [
        'coming-soon',
        'login',
        'logout',
        'up',
        'livewire/*',
        'paystack/*',
        'email/unsubscribe/*',
    ],

];
