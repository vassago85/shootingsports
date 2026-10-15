<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum OrganisationUserRole: string implements HasLabel
{
    case MatchDirector = 'match_director';
    case Admin = 'admin';
    case Editor = 'editor';
    case Secretary = 'secretary';

    public function getLabel(): string
    {
        return match ($this) {
            self::MatchDirector => 'Match director',
            self::Admin => 'Admin',
            self::Editor => 'Editor',
            self::Secretary => 'Secretary',
        };
    }

    /**
     * @return list<self>
     */
    public static function canManageEvents(): array
    {
        return [self::MatchDirector, self::Admin, self::Editor];
    }

    /**
     * Roles that may manage a club's member register — admins run the
     * club, secretaries typically own membership admin.
     *
     * @return list<self>
     */
    public static function canManageMembers(): array
    {
        return [self::Admin, self::Secretary];
    }
}
