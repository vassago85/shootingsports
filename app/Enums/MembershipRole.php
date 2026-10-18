<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

/**
 * Phase B — role a user has inside an organisation or provider.
 * Shared by `organisation_user` and `provider_user` so the desk code
 * can treat both the same.
 */
enum MembershipRole: string implements HasColor, HasLabel
{
    case Owner = 'owner';
    case Editor = 'editor';

    public function getLabel(): string
    {
        return match ($this) {
            self::Owner => 'Owner',
            self::Editor => 'Editor',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Owner => 'primary',
            self::Editor => 'gray',
        };
    }
}
