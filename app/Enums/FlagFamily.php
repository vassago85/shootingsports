<?php

namespace App\Enums;

use App\Enums\Concerns\HasLabelFromValue;
use Filament\Support\Contracts\HasLabel;

enum FlagFamily: string implements HasLabel
{
    use HasLabelFromValue;

    case Access = 'access';
    case Competition = 'competition';
    case Logistics = 'logistics';
}
