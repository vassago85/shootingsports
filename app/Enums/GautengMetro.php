<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum GautengMetro: string implements HasLabel
{
    case Pretoria = 'pretoria';
    case Johannesburg = 'johannesburg';
    case Vaal = 'vaal';

    public function getLabel(): string
    {
        return match ($this) {
            self::Pretoria => 'Pretoria',
            self::Johannesburg => 'Johannesburg',
            self::Vaal => 'Vaal',
        };
    }
}
