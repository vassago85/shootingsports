<?php

namespace App\Enums;

use App\Enums\Concerns\HasLabelFromValue;
use Filament\Support\Contracts\HasLabel;

/**
 * Club desk subscription. Separate from the shooter {@see Plan} on User.
 * Free clubs keep the public listing and event upload. Club unlocks
 * member tools, on-site entries, and the club embed.
 */
enum ClubPlan: string implements HasLabel
{
    use HasLabelFromValue;

    case Free = 'free';
    case Club = 'club';
}
