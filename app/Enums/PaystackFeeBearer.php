<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

/**
 * Who carries Paystack's own processing fee on a split charge.
 * `account` is the platform (the only Paystack merchant).
 * `subaccount` is the club.
 */
enum PaystackFeeBearer: string implements HasLabel
{
    case Account = 'account';
    case Subaccount = 'subaccount';

    public function getLabel(): string
    {
        return match ($this) {
            self::Account => 'Platform',
            self::Subaccount => 'Club',
        };
    }
}
