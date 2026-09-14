<?php

namespace App\Enums;

use App\Enums\Concerns\HasLabelFromValue;
use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum OrganisationType: string implements HasColor, HasLabel
{
    use HasLabelFromValue;

    case Club = 'club';
    case Federation = 'federation';
    case Association = 'association';
    case ProvincialBody = 'provincial_body';
    /** Branded recurring match (e.g. monthly gong series) — not a membership body. */
    case Series = 'series';

    public function getColor(): string
    {
        return match ($this) {
            self::Club => 'info',
            self::Series => 'warning',
            self::Federation => 'primary',
            self::Association => 'teal',
            self::ProvincialBody => 'cyan',
        };
    }
}
