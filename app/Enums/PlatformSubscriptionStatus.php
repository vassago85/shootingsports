<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum PlatformSubscriptionStatus: string implements HasLabel
{
    case Trial = 'trial';
    case TrialExpired = 'trial_expired';
    case Active = 'active';
    case PastDue = 'past_due';
    case Suspended = 'suspended';
    case Cancelled = 'cancelled';

    public function getLabel(): string
    {
        return match ($this) {
            self::Trial => 'Trial',
            self::TrialExpired => 'Trial expired',
            self::Active => 'Active',
            self::PastDue => 'Past due',
            self::Suspended => 'Suspended',
            self::Cancelled => 'Cancelled',
        };
    }

    public function isReadOnly(): bool
    {
        return in_array($this, [self::TrialExpired, self::PastDue, self::Suspended], true);
    }
}
