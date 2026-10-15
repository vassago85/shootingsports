<?php

namespace App\Enums;

use App\Enums\Concerns\HasLabelFromValue;
use Filament\Support\Contracts\HasLabel;

enum ClubMemberCategory: string implements HasLabel
{
    use HasLabelFromValue;

    case Senior = 'senior';
    case Junior = 'junior';
    case Family = 'family';
    case Pensioner = 'pensioner';
    case Life = 'life';
    case Honorary = 'honorary';
}
