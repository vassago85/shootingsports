<?php

use App\Enums\Plan;

/*
|--------------------------------------------------------------------------
| Plan limits
|--------------------------------------------------------------------------
|
| Single source of truth for freemium caps. Values are integers (a hard
| cap) or null (unlimited). Never hardcode any of these numbers anywhere
| else in the codebase — always read through App\Models\Concerns\HasPlan.
|
| Guard rails:
|   - No cap here is allowed to gate access to public data. Public
|     calendar, iCal feeds, embed, directory, discipline / province
|     landings and match pages stay free forever.
|   - Caps only apply to personalisation volume (how many follows you
|     may keep, how many searches you may save, how deep your history
|     window goes) and to convenience features (export, household).
|
*/

return [
    /*
    |--------------------------------------------------------------------------
    | Pro sales
    |--------------------------------------------------------------------------
    |
    | Off until we are ready to sell. Trials, checkout, prices and the
    | upgrade prompts stay hidden. Existing plan caps still apply.
    | Tests opt back in via PRO_FEATURES=true in phpunit.xml.
    |
    */
    'pro_enabled' => (bool) env('PRO_FEATURES', false),

    Plan::Free->value => [
        'follows' => 3,
        'saved_searches' => 1,
        'history_months' => 12,
        'export' => false,
        'household_profiles' => 1,
        // Personal shooting log. Free = 3 slots at any time (delete
        // an old one to log a new one). Pro = unlimited history for
        // dedicated-status renewal PDFs.
        'attended_events_slots' => 3,
    ],

    Plan::Pro->value => [
        'follows' => null,
        'saved_searches' => null,
        'history_months' => null,
        'export' => true,
        'household_profiles' => 4,
        'attended_events_slots' => null,
    ],

    /*
    |--------------------------------------------------------------------------
    | Public pricing
    |--------------------------------------------------------------------------
    |
    | Numbers are the single source of truth for BOTH:
    |   - what we display in the UI, and
    |   - what we create in Paystack when `php artisan paystack:setup-plans`
    |     runs (as ZAR cents — 29900 = R299.00).
    |
    | If either value changes, re-run the setup command so the Paystack
    | plans stay in sync. Existing subscribers keep their old price until
    | they cancel and re-subscribe (Paystack does not retroactively
    | reprice active subscriptions).
    |
    */
    'pricing' => [
        'annual' => [
            'amount_cents' => 29900,
            'display' => 'R299 / year',
            'summary' => 'Save R49 versus monthly',
        ],
        'monthly' => [
            'amount_cents' => 2900,
            'display' => 'R29 / month',
            'summary' => 'Cancel any time',
        ],
    ],
];
