<?php

namespace App\Enums;

use App\Enums\Concerns\HasLabelFromValue;
use Filament\Support\Contracts\HasLabel;

enum EventKind: string implements HasLabel
{
    use HasLabelFromValue;

    case Competition = 'competition';
    case Training = 'training';
}
