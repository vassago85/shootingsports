<?php

use App\Enums\ClubPlan;

/*
|--------------------------------------------------------------------------
| Club subscription
|--------------------------------------------------------------------------
|
| Separate from the shooter Pro plan in config/plans.php. Uploading
| events, the public club page, and a link out to the organiser's own
| entry system stay free. The keys below are the tools a Club
| subscription unlocks.
|
| Prices are placeholders. Fill amount_cents and display before
| turning CLUB_PLANS_ENABLED on. Do not invent a price here.
|
*/

return [
    /*
    | Off until prices and Paystack plan codes are set. The lock page
    | shows a subscribe button only while this is true. The feature
    | checks below still apply when it is false.
    */
    'enabled' => (bool) env('CLUB_PLANS_ENABLED', false),

    ClubPlan::Free->value => [
        'members' => false,
        'invoices' => false,
        'guest_days' => false,
        'reports' => false,
        'on_site_entries' => false,
        'embed' => false,
    ],

    ClubPlan::Club->value => [
        'members' => true,
        'invoices' => true,
        'guest_days' => true,
        'reports' => true,
        'on_site_entries' => true,
        'embed' => true,
    ],

    'pricing' => [
        'annual' => [
            'amount_cents' => null, // TODO
            'display' => null, // TODO
            'summary' => null, // TODO
        ],
        'monthly' => [
            'amount_cents' => null, // TODO
            'display' => null, // TODO
            'summary' => null, // TODO
        ],
    ],
];
