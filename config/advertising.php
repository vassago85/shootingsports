<?php

/*
|--------------------------------------------------------------------------
| Advertising rate card
|--------------------------------------------------------------------------
|
| Public rate card rendered on /advertise and used by ProviderResource
| to show tier pricing in Filament helper text. Prices are display
| values (integer rands, formatted at render time) — no payment
| provider is wired in this phase.
|
| Every product has a stable `key` (used in enquiry context) and,
| optionally, an ad-slot `page` + `slot` pair. When both are set the
| view can pull live dimension / availability info from AdSlot.
|
| Keys used elsewhere in the codebase:
|   - featured               → ProviderResource "Enhanced listing" helper
|   - discover_placement     → Discover / disciplines category sponsor
|   - discipline_exclusive   → single-discipline hero + sponsor
|   - home_leaderboard       → home page banner
|   - calendar_leaderboard   → calendar page banner
|   - newsletter             → editorial newsletter mention
|
*/

use App\Enums\AdPage;
use App\Enums\PlacementSlot;

return [

    'products' => [
        'featured' => [
            'key' => 'featured',
            'name' => 'Enhanced supplier listing',
            'summary' => 'Move above free listings in the Industry directory, add photos, opening hours, contact form.',
            'price_per_month_cents' => 39900, // R399/mo
            'price_display' => 'R399 / month',
            'page' => null,
            'slot' => null,
            'audience' => 'Every visitor to your industry page + every discipline / calendar filter that surfaces you.',
        ],

        'discover_placement' => [
            'key' => 'discover_placement',
            'name' => 'Discover placement',
            'summary' => 'Category sponsor slot on a discipline landing page — reticle-native, no display-ad chrome.',
            'price_per_month_cents' => 180000, // matches AdSlotSeeder discipline sponsor
            'price_display' => 'R1 800 / month',
            'page' => AdPage::Disciplines->value,
            'slot' => PlacementSlot::CategorySponsor->value,
            'audience' => 'Shooters browsing your discipline family, filtered by province.',
        ],

        'discipline_exclusive' => [
            'key' => 'discipline_exclusive',
            'name' => 'Discipline exclusive',
            'summary' => 'Sole sponsor of a single discipline page for the quarter — includes the sponsor slot and a top-of-calendar strip on that discipline.',
            'price_per_month_cents' => 350000,
            'price_display' => 'R3 500 / month · quarterly minimum',
            'page' => AdPage::Disciplines->value,
            'slot' => PlacementSlot::CategorySponsor->value,
            'audience' => 'Every shooter looking at this discipline page + its province filters.',
        ],

        'home_leaderboard' => [
            'key' => 'home_leaderboard',
            'name' => 'Home leaderboard',
            'summary' => 'The top banner on the home page. One advertiser at a time, rotated with a housekeeping slot.',
            'price_per_month_cents' => 250000,
            'price_display' => 'R2 500 / month',
            'page' => AdPage::Home->value,
            'slot' => PlacementSlot::Leaderboard->value,
            'audience' => 'Every visitor to shootingsports.co.za.',
        ],

        'calendar_leaderboard' => [
            'key' => 'calendar_leaderboard',
            'name' => 'Calendar leaderboard',
            'summary' => 'Banner at the top of the national match calendar.',
            'price_per_month_cents' => 200000,
            'price_display' => 'R2 000 / month',
            'page' => AdPage::Calendar->value,
            'slot' => PlacementSlot::Leaderboard->value,
            'audience' => 'Shooters actively looking for the next match, filtered by discipline, province and driving distance.',
        ],

        'newsletter' => [
            'key' => 'newsletter',
            'name' => 'Newsletter mention',
            'summary' => 'A single-paragraph mention with a link in the weekly / monthly shooter digest.',
            'price_per_month_cents' => 75000,
            'price_display' => 'R750 per issue',
            'page' => null,
            'slot' => null,
            'audience' => 'Registered shooters who have opted in to the digest.',
        ],
    ],

    'commitments' => [
        'One national register — no discipline is favoured, no federation buys editorial.',
        'Ads are labelled and never dressed up as match listings.',
        'We do not sell firearms, take a cut of a transfer or broker ammunition — advertising is our only revenue.',
    ],
];
