<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum PaymentRefundKind: string implements HasLabel
{
    case Refund = 'refund';
    case Chargeback = 'chargeback';

    public function getLabel(): string
    {
        return match ($this) {
            self::Refund => 'Refund',
            self::Chargeback => 'Chargeback',
        };
    }
}
