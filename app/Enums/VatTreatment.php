<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

/**
 * How VAT applies to the platform fee. Kept as configuration so the
 * accountant can confirm the treatment without a code change.
 */
enum VatTreatment: string implements HasLabel
{
    case Exclusive = 'exclusive';
    case Inclusive = 'inclusive';
    case Exempt = 'exempt';

    public function getLabel(): string
    {
        return match ($this) {
            self::Exclusive => 'VAT exclusive',
            self::Inclusive => 'VAT inclusive',
            self::Exempt => 'VAT exempt',
        };
    }
}
