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
    Plan::Free->value => [
        'follows' => 3,
        'saved_searches' => 1,
        'history_months' => 12,
        'export' => false,
        'household_profiles' => 1,
    ],

    Plan::Pro->value => [
        'follows' => null,
        'saved_searches' => null,
        'history_months' => null,
        'export' => true,
        'household_profiles' => 4,
    ],

    /*
    |--------------------------------------------------------------------------
    | Public pricing
    |--------------------------------------------------------------------------
    |
    | Rendered in the UpgradePrompt and on the pricing block. No payment
    | provider is wired in this phase — these are display values only.
    |
    */
    'pricing' => [
        'monthly' => 'R29.99 / month',
        'annual' => 'R249 / year',
    ],
];
