<?php

namespace App\Enums;

use App\Enums\Concerns\HasLabelFromValue;
use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum VerificationState: string implements HasColor, HasLabel
{
    use HasLabelFromValue;

    case Verified = 'verified';
    case Ageing = 'ageing';
    case Unconfirmed = 'unconfirmed';
    case Archived = 'archived';

    public function getColor(): string
    {
        return match ($this) {
            self::Verified => 'success',
            self::Ageing => 'warning',
            self::Unconfirmed => 'gray',
            self::Archived => 'danger',
        };
    }
}
