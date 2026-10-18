<?php

use App\Models\Setting;
use App\Support\PlatformSettings;

it('reads platform fee basis points from the settings table at runtime', function () {
    Setting::put('platform.fee_bps', '250');

    PlatformSettings::apply();

    expect(config('platform.fee_bps'))->toBe(250);
});

it('writes and applies all platform billing keys via save()', function () {
    PlatformSettings::save([
        'fee_bps' => 275,
        'vat_bps' => 1500,
        'payout_admin_fee_cents' => 5000,
        'trial_days' => 30,
        'grace_window_days' => 7,
        'single_event_price_cents' => 75000,
    ]);

    expect(config('platform.fee_bps'))->toBe(275)
        ->and(config('platform.vat_bps'))->toBe(1500)
        ->and(config('platform.payout_admin_fee_cents'))->toBe(5000)
        ->and(config('platform.trial_days'))->toBe(30)
        ->and(config('platform.grace_window_days'))->toBe(7)
        ->and(config('platform.single_event_price_cents'))->toBe(75000);
});
