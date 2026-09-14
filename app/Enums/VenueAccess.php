<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum VenueAccess: string implements HasLabel
{
    case Public = 'public';
    case MembersOnly = 'members_only';
    case GuestByArrangement = 'guest_by_arrangement';

    public function getLabel(): string
    {
        return match ($this) {
            self::Public => 'Public',
            self::MembersOnly => 'Members only',
            self::GuestByArrangement => 'Guest by arrangement',
        };
    }
}
