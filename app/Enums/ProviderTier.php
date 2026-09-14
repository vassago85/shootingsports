<?php

namespace App\Enums;

use App\Enums\Concerns\HasLabelFromValue;
use Filament\Support\Contracts\HasLabel;

enum ProviderTier: string implements HasLabel
{
    use HasLabelFromValue;

    case Free = 'free';
    case Verified = 'verified';
    case Featured = 'featured';
}
