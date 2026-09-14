<?php

namespace App\Enums;

use App\Enums\Concerns\HasLabelFromValue;
use Filament\Support\Contracts\HasLabel;

enum DisciplineFamily: string implements HasLabel
{
    use HasLabelFromValue;

    case Rifle = 'rifle';
    case Handgun = 'handgun';
    case Shotgun = 'shotgun';
    case Airgun = 'airgun';
    case Multi = 'multi';
}
