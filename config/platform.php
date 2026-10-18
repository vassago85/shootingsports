<?php

return [
    'fee_bps' => (int) env('PLATFORM_FEE_BPS', 200),
    'vat_bps' => (int) env('PLATFORM_VAT_BPS', 1500),
    'payout_admin_fee_cents' => (int) env('PLATFORM_PAYOUT_ADMIN_FEE_CENTS', 5000),
    'trial_days' => (int) env('PLATFORM_TRIAL_DAYS', 30),
    'grace_window_days' => (int) env('PLATFORM_GRACE_WINDOW_DAYS', 30),
    'dunning_retry_days' => [1, 4, 7, 14],
    'single_event_price_cents' => (int) env('PLATFORM_SINGLE_EVENT_PRICE_CENTS', 75000),
    'tier_prices_cents' => [
        'micro' => ['monthly' => 10000, 'annual' => 100000],
        'standard' => ['monthly' => 50000, 'annual' => 500000],
        'large' => ['monthly' => 80000, 'annual' => 800000],
    ],
    'tier_member_caps' => [
        'micro' => 50,
        'standard' => 250,
        'large' => 1000,
    ],
];
