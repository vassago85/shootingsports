<?php

namespace App\Enums;

use App\Enums\Concerns\HasLabelFromValue;
use Filament\Support\Contracts\HasLabel;

enum SubmissionType: string implements HasLabel
{
    use HasLabelFromValue;

    case Organisation = 'organisation';
    case Venue = 'venue';
    case Event = 'event';
    case Provider = 'provider';
}
