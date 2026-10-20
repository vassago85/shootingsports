<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum PayoutDocumentType: string implements HasLabel
{
    case IdentityNumber = 'identityNumber';
    case BusinessRegistrationNumber = 'businessRegistrationNumber';

    public function getLabel(): string
    {
        return match ($this) {
            self::IdentityNumber => 'SA ID number',
            self::BusinessRegistrationNumber => 'Business registration number',
        };
    }
}
