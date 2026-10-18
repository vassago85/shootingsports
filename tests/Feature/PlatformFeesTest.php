<?php

use App\Support\PlatformFees;

it('computes 2 percent fee on R600 as R12', function () {
    config()->set('platform.fee_bps', 200);

    expect(PlatformFees::feeCents(60000))->toBe(1200);
});

it('computes 15 percent VAT on R12 fee as R1.80', function () {
    config()->set('platform.vat_bps', 1500);

    expect(PlatformFees::feeVatCents(1200))->toBe(180);
});

it('makes exclusive R100 into inclusive R115', function () {
    config()->set('platform.vat_bps', 1500);

    expect(PlatformFees::vatInclusiveCents(10000))->toBe(11500);
});

it('splits a R600 gross into the full breakdown', function () {
    config()->set('platform.fee_bps', 200);
    config()->set('platform.vat_bps', 1500);

    $split = PlatformFees::splitFor(60000);

    expect($split)->toMatchArray([
        'gross' => 60000,
        'fee' => 1200,
        'fee_vat' => 180,
        // net_to_club_estimate = gross - fee - fee_vat (Paystack fee unknown here)
        'net_to_club_estimate' => 58620,
    ]);
});
