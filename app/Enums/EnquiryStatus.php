<?php

namespace App\Enums;

use App\Enums\Concerns\HasLabelFromValue;
use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum EnquiryStatus: string implements HasColor, HasLabel
{
    use HasLabelFromValue;

    case PendingConfirmation = 'pending_confirmation';
    case New = 'new';
    case Read = 'read';
    case Closed = 'closed';

    public function getLabel(): string
    {
        return match ($this) {
            self::PendingConfirmation => 'Pending email confirmation',
            default => str($this->value)->replace('_', ' ')->title()->toString(),
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::PendingConfirmation => 'gray',
            self::New => 'warning',
            self::Read => 'info',
            self::Closed => 'gray',
        };
    }
}
