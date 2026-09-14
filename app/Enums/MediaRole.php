<?php

namespace App\Enums;

use App\Enums\Concerns\HasLabelFromValue;
use Filament\Support\Contracts\HasLabel;

enum MediaRole: string implements HasLabel
{
    use HasLabelFromValue;

    case Banner = 'banner';
    case Logo = 'logo';
    case Gallery = 'gallery';
    case Og = 'og';
}
