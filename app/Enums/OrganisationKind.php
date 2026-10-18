<?php

namespace App\Enums;

enum OrganisationKind: string
{
    case Club = 'club';
    case SingleEvent = 'single_event';

    public function label(): string
    {
        return match ($this) {
            self::Club => 'Club',
            self::SingleEvent => 'Single Event',
        };
    }
}
