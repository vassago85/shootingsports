<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum AdPage: string implements HasLabel
{
    case Home = 'home';
    case Calendar = 'calendar';
    case Suppliers = 'suppliers';
    case Ranges = 'ranges';
    case Disciplines = 'disciplines';
    case Matches = 'matches';

    public function getLabel(): string
    {
        return match ($this) {
            self::Home => 'Home',
            self::Calendar => 'Calendar',
            self::Suppliers => 'Suppliers',
            self::Ranges => 'Ranges',
            self::Disciplines => 'Disciplines',
            self::Matches => 'Match detail',
        };
    }
}
