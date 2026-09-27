<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum EntryPaymentStatus: string implements HasLabel
{
    case External = 'external';
    case Pending = 'pending';
    case Paid = 'paid';

    public function getLabel(): string
    {
        return match ($this) {
            self::External => 'Pay the club',
            self::Pending => 'Payment pending',
            self::Paid => 'Paid',
        };
    }
}
