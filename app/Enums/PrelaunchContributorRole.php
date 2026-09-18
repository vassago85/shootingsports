<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum PrelaunchContributorRole: string implements HasLabel
{
    case Range = 'range';
    case MatchDirector = 'match_director';
    case Club = 'club';
    case Business = 'business';

    public function getLabel(): string
    {
        return match ($this) {
            self::Range => 'Range',
            self::MatchDirector => 'Match director',
            self::Club => 'Club',
            self::Business => 'Business',
        };
    }

    public function subjectLine(): string
    {
        return 'Pre-launch: '.$this->getLabel();
    }
}
