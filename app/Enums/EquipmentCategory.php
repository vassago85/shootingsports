<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum EquipmentCategory: string implements HasLabel
{
    case Rifle = 'rifle';
    case Pistol = 'pistol';
    case Shotgun = 'shotgun';
    case Optic = 'optic';
    case Suppressor = 'suppressor';
    case Other = 'other';

    public function getLabel(): string
    {
        return match ($this) {
            self::Rifle => 'Rifle',
            self::Pistol => 'Pistol',
            self::Shotgun => 'Shotgun',
            self::Optic => 'Optic',
            self::Suppressor => 'Suppressor',
            self::Other => 'Other',
        };
    }
}
