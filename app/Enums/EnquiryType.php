<?php

namespace App\Enums;

use App\Enums\Concerns\HasLabelFromValue;
use Filament\Support\Contracts\HasLabel;

enum EnquiryType: string implements HasLabel
{
    use HasLabelFromValue;

    case General = 'general';
    case Advertise = 'advertise';
    case Listing = 'listing';
    case ProWaitlist = 'pro_waitlist';
    case MdSignup = 'md_signup';
    case PrelaunchContributor = 'prelaunch_contributor';

    public function getLabel(): string
    {
        return match ($this) {
            self::PrelaunchContributor => 'Pre-launch contributor',
            default => str($this->value)->replace('_', ' ')->title()->toString(),
        };
    }
}
