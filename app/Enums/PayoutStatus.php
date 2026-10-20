<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum PayoutStatus: string implements HasColor, HasLabel
{
    case Draft = 'draft';
    case ValidationFailed = 'validation_failed';
    case PendingApproval = 'pending_approval';
    case Active = 'active';
    case Suspended = 'suspended';

    public function getLabel(): string
    {
        return match ($this) {
            self::Draft => 'Draft',
            self::ValidationFailed => 'Validation failed',
            self::PendingApproval => 'Pending approval',
            self::Active => 'Active',
            self::Suspended => 'Suspended',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Draft => 'gray',
            self::ValidationFailed => 'danger',
            self::PendingApproval => 'warning',
            self::Active => 'success',
            self::Suspended => 'danger',
        };
    }

    public function deskMessage(): string
    {
        return match ($this) {
            self::Draft => 'Add a bank account so shooters can pay entry fees on this site.',
            self::ValidationFailed => 'The bank could not confirm these details. Correct them and submit again.',
            self::PendingApproval => 'Banking details received. Payouts start once approved.',
            self::Active => 'Payouts are active. Entry fees settle to this account.',
            self::Suspended => 'Payouts are paused. Shooters cannot pay entry fees online.',
        };
    }
}
