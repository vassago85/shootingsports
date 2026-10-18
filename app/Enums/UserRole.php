<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

/**
 * Phase B — single source of truth for the admin / staff / shooter
 * capability split, replacing the `is_staff` boolean. Admin is
 * reserved for the handful of people who can promote others to
 * staff; staff is the day-to-day moderator role; user is everyone
 * else (shooters, directors-via-pivot, industry-via-pivot).
 */
enum UserRole: string implements HasColor, HasLabel
{
    case User = 'user';
    case Staff = 'staff';
    case Admin = 'admin';

    public function getLabel(): string
    {
        return match ($this) {
            self::User => 'Shooter',
            self::Staff => 'Staff',
            self::Admin => 'Admin',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::User => 'gray',
            self::Staff => 'success',
            self::Admin => 'danger',
        };
    }

    /**
     * Admin and staff both reach `/admin`; only admin can promote
     * others. Phase C flips the panel gate to use this.
     */
    public function canAccessAdminPanel(): bool
    {
        return in_array($this, [self::Staff, self::Admin], true);
    }
}
