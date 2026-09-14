<?php

namespace App\Enums;

use App\Enums\Concerns\HasLabelFromValue;
use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum EnquiryStatus: string implements HasColor, HasLabel
{
    use HasLabelFromValue;

    case New = 'new';
    case Read = 'read';
    case Closed = 'closed';

    public function getColor(): string
    {
        return match ($this) {
            self::New => 'warning',
            self::Read => 'info',
            self::Closed => 'gray',
        };
    }
}
