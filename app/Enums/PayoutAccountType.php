<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum PayoutAccountType: string implements HasLabel
{
    case Personal = 'personal';
    case Business = 'business';

    public function getLabel(): string
    {
        return match ($this) {
            self::Personal => 'Personal',
            self::Business => 'Business',
        };
    }

    public function documentType(): PayoutDocumentType
    {
        return match ($this) {
            self::Personal => PayoutDocumentType::IdentityNumber,
            self::Business => PayoutDocumentType::BusinessRegistrationNumber,
        };
    }
}
