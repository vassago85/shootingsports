<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum EntryCollection: string implements HasLabel
{
    case External = 'external';
    case Paystack = 'paystack';

    public function getLabel(): string
    {
        return match ($this) {
            self::External => 'Record the entry. The shooter pays the club.',
            self::Paystack => 'Take the entry fee on this site (Paystack).',
        };
    }
}
