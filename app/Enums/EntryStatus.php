<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum EntryStatus: string implements HasLabel
{
    case Entered = 'entered';
    case Withdrawn = 'withdrawn';
    case PendingPayment = 'pending_payment';

    public function getLabel(): string
    {
        return match ($this) {
            self::Entered => 'Entered',
            self::Withdrawn => 'Withdrawn',
            self::PendingPayment => 'Awaiting payment',
        };
    }
}
