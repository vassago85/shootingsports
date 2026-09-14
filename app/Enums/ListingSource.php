<?php

namespace App\Enums;

use App\Enums\Concerns\HasLabelFromValue;
use Filament\Support\Contracts\HasLabel;

enum ListingSource: string implements HasLabel
{
    use HasLabelFromValue;

    case Staff = 'staff';
    case Submission = 'submission';
    case Import = 'import';
    case Claimed = 'claimed';
}
