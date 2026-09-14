<?php

namespace App\Enums;

use App\Enums\Concerns\HasLabelFromValue;
use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum EventLevel: string implements HasColor, HasLabel
{
    use HasLabelFromValue;

    case Club = 'club';
    case Series = 'series';
    case Provincial = 'provincial';
    case National = 'national';
    case International = 'international';

    public function getColor(): string
    {
        return match ($this) {
            self::Club => 'gray',
            self::Series => 'warning',
            self::Provincial => 'info',
            self::National => 'primary',
            self::International => 'success',
        };
    }
}
