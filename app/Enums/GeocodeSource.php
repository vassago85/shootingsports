<?php

namespace App\Enums;

use App\Enums\Concerns\HasLabelFromValue;
use Filament\Support\Contracts\HasLabel;

enum GeocodeSource: string implements HasLabel
{
    use HasLabelFromValue;

    case Nominatim = 'nominatim';
    case Staff = 'staff';
    case Google = 'google';
}
