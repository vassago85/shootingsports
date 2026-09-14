<?php

namespace App\Enums;

use App\Enums\Concerns\HasLabelFromValue;
use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum ListingStatus: string implements HasColor, HasLabel
{
    use HasLabelFromValue;

    case Published = 'published';
    case Pending = 'pending';
    case Archived = 'archived';

    public function getColor(): string
    {
        return match ($this) {
            self::Published => 'success',
            self::Pending => 'warning',
            self::Archived => 'danger',
        };
    }
}
