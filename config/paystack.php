<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Match-entry split payments
    |--------------------------------------------------------------------------
    |
    | The platform is the only Paystack merchant. Each club is a subaccount
    | (a settlement record), never its own Paystack login. Amounts are cents.
    |
    | The flat platform fee and its VAT treatment stay here so they can
    | change after the accountant confirms them. Do not hardcode either
    | in a service. `exclusive` adds VAT on top of the fee. `inclusive`
    | treats the configured fee as already including VAT. `exempt`
    | calculates no VAT.
    |
    | `fee_bearer` is who carries Paystack's processing fee:
    | `account` = platform, `subaccount` = club.
    |
    */

    'platform_fee_cents' => (int) env('PAYSTACK_PLATFORM_FEE_CENTS', 0),

    'vat_treatment' => env('PAYSTACK_VAT_TREATMENT', 'exclusive'),

    'vat_rate_basis_points' => (int) env('PAYSTACK_VAT_RATE_BASIS_POINTS', 1500),

    'fee_bearer' => env('PAYSTACK_FEE_BEARER', 'account'),

    'approval_stale_hours' => (int) env('PAYSTACK_APPROVAL_STALE_HOURS', 48),

];
