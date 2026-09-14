<?php

namespace App\Enums;

use App\Enums\Concerns\HasLabelFromValue;
use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum SubmissionStatus: string implements HasColor, HasLabel
{
    use HasLabelFromValue;

    case Pending = 'pending';
    case Merged = 'merged';
    case Rejected = 'rejected';

    public function getColor(): string
    {
        return match ($this) {
            self::Pending => 'warning',
            self::Merged => 'success',
            self::Rejected => 'danger',
        };
    }
}
