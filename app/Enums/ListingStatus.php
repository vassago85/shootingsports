<?php

namespace App\Enums;

use App\Enums\Concerns\HasLabelFromValue;
use Filament\Support\Contracts\HasLabel;

enum ListingStatus: string implements HasLabel
{
    use HasLabelFromValue;

    case Published = 'published';
    case Pending = 'pending';
    case Archived = 'archived';
}
