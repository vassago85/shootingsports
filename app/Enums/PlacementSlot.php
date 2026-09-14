<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum PlacementSlot: string implements HasLabel
{
    case Leaderboard = 'leaderboard';
    case CategorySponsor = 'category_sponsor';
    case InFeedNative = 'in_feed_native';
    case DisciplineTakeover = 'discipline_takeover';
    case Mailer = 'mailer';

    public function getLabel(): string
    {
        return match ($this) {
            self::Leaderboard => 'Leaderboard',
            self::CategorySponsor => 'Category sponsor',
            self::InFeedNative => 'In-feed native',
            self::DisciplineTakeover => 'Discipline takeover',
            self::Mailer => 'Mailer',
        };
    }
}
