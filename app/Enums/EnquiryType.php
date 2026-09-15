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
}
