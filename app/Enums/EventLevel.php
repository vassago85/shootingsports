<?php

namespace App\Enums;

use App\Enums\Concerns\HasLabelFromValue;
use Filament\Support\Contracts\HasLabel;

enum EventLevel: string implements HasLabel
{
    use HasLabelFromValue;

    case Club = 'club';
    case Provincial = 'provincial';
    case National = 'national';
    case International = 'international';
}
