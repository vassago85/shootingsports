<?php

namespace App\Enums;

use App\Enums\Concerns\HasLabelFromValue;
use Filament\Support\Contracts\HasLabel;

enum DisciplineFamily: string implements HasLabel
{
    use HasLabelFromValue;

    case Rifle = 'rifle';
    case Handgun = 'handgun';
    case Shotgun = 'shotgun';
    case Airgun = 'airgun';
    case Multi = 'multi';

    /**
     * Decorative photo for family tiles. Airgun and multi have no
     * matching picture in the public set, so those tiles stay plain.
     */
    public function cardPhoto(): ?string
    {
        return match ($this) {
            self::Rifle => 'images/divisions/bolt-action-rifle.jpg',
            self::Handgun => 'images/divisions/handgun.jpg',
            self::Shotgun => 'images/divisions/shotgun.jpg',
            self::Airgun, self::Multi => null,
        };
    }
}
