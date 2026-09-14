<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum OrganisationUserRole: string implements HasLabel
{
    case MatchDirector = 'match_director';
    case Admin = 'admin';
    case Editor = 'editor';

    public function getLabel(): string
    {
        return match ($this) {
            self::MatchDirector => 'Match director',
            self::Admin => 'Admin',
            self::Editor => 'Editor',
        };
    }

    /**
     * @return list<self>
     */
    public static function canManageEvents(): array
    {
        return [self::MatchDirector, self::Admin, self::Editor];
    }
}
