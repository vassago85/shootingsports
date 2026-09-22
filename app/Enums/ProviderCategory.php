<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum ProviderCategory: string implements HasLabel
{
    case Gunsmith = 'gunsmith';
    case Dealer = 'dealer';
    case Distributor = 'distributor';
    case Instructor = 'instructor';
    case ReloadingComponents = 'reloading_components';
    case Ammunition = 'ammunition';
    case Optics = 'optics';
    case ChassisStocks = 'chassis_stocks';
    case BallisticsElectronics = 'ballistics_electronics';
    case RangeEquipment = 'range_equipment';
    case SafesStorage = 'safes_storage';
    case Transport = 'transport';
    case Insurance = 'insurance';

    public function urlSlug(): string
    {
        return str_replace('_', '-', $this->value);
    }

    public static function fromUrlSlug(string $slug): ?self
    {
        return self::tryFrom(str_replace('-', '_', $slug));
    }

    public function getLabel(): string
    {
        return match ($this) {
            self::Gunsmith => 'Gunsmith',
            self::Dealer => 'Dealer',
            self::Distributor => 'Distributor',
            self::Instructor => 'Instructor / academy',
            self::ReloadingComponents => 'Reloading components',
            self::Ammunition => 'Ammunition',
            self::Optics => 'Optics',
            self::ChassisStocks => 'Chassis & stocks',
            self::BallisticsElectronics => 'Ballistics & electronics',
            self::RangeEquipment => 'Range equipment',
            self::SafesStorage => 'Safes & storage',
            self::Transport => 'Transport',
            self::Insurance => 'Insurance',
        };
    }
}
