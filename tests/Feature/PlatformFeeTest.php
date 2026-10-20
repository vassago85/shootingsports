<?php

use App\Enums\PaystackFeeBearer;
use App\Enums\VatTreatment;
use App\Services\Paystack\PlatformFee;

beforeEach(function () {
    config([
        'paystack.platform_fee_cents' => 1000,
        'paystack.vat_treatment' => 'exclusive',
        'paystack.vat_rate_basis_points' => 1500,
        'paystack.fee_bearer' => 'account',
    ]);
});

it('adds vat on top of an exclusive platform fee', function () {
    $quote = (new PlatformFee)->quote(15000);

    expect($quote->platformFeeCents)->toBe(1000)
        ->and($quote->vatOnFeeCents)->toBe(150)
        ->and($quote->transactionChargeCents)->toBe(1150)
        ->and($quote->estimatedClubShareCents)->toBe(13850)
        ->and($quote->bearer)->toBe(PaystackFeeBearer::Account)
        ->and($quote->vatTreatment)->toBe(VatTreatment::Exclusive);
});

it('extracts vat from an inclusive platform fee', function () {
    config([
        'paystack.vat_treatment' => 'inclusive',
        'paystack.platform_fee_cents' => 1150,
        'paystack.fee_bearer' => 'subaccount',
    ]);

    $quote = (new PlatformFee)->quote(15000);

    expect($quote->transactionChargeCents)->toBe(1150)
        ->and($quote->vatOnFeeCents)->toBe(150)
        ->and($quote->platformFeeCents)->toBe(1000)
        ->and($quote->bearer)->toBe(PaystackFeeBearer::Subaccount);
});

it('charges no vat when the fee is exempt', function () {
    config(['paystack.vat_treatment' => 'exempt']);

    $quote = (new PlatformFee)->quote(15000);

    expect($quote->vatOnFeeCents)->toBe(0)
        ->and($quote->transactionChargeCents)->toBe(1000)
        ->and($quote->platformFeeCents)->toBe(1000)
        ->and($quote->vatTreatment)->toBe(VatTreatment::Exempt);
});

it('refuses a fee that would leave the club with nothing', function () {
    config(['paystack.platform_fee_cents' => 20000]);

    expect(fn () => (new PlatformFee)->quote(15000))
        ->toThrow(InvalidArgumentException::class);
});

it('refuses an unknown vat treatment', function () {
    config(['paystack.vat_treatment' => 'maybe']);

    expect(fn () => (new PlatformFee)->quote(15000))
        ->toThrow(InvalidArgumentException::class, 'VAT treatment');
});
