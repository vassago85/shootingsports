<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum Province: string implements HasLabel
{
    case EasternCape = 'eastern_cape';
    case FreeState = 'free_state';
    case Gauteng = 'gauteng';
    case KwaZuluNatal = 'kwazulu_natal';
    case Limpopo = 'limpopo';
    case Mpumalanga = 'mpumalanga';
    case NorthernCape = 'northern_cape';
    case NorthWest = 'north_west';
    case WesternCape = 'western_cape';

    public function getLabel(): string
    {
        return match ($this) {
            self::EasternCape => 'Eastern Cape',
            self::FreeState => 'Free State',
            self::Gauteng => 'Gauteng',
            self::KwaZuluNatal => 'KwaZulu-Natal',
            self::Limpopo => 'Limpopo',
            self::Mpumalanga => 'Mpumalanga',
            self::NorthernCape => 'Northern Cape',
            self::NorthWest => 'North West',
            self::WesternCape => 'Western Cape',
        };
    }

    public function urlSlug(): string
    {
        return str_replace('_', '-', $this->value);
    }

    public static function fromUrlSlug(string $slug): ?self
    {
        return self::tryFrom(str_replace('-', '_', $slug));
    }

    public function code(): string
    {
        return match ($this) {
            self::EasternCape => 'EC',
            self::FreeState => 'FS',
            self::Gauteng => 'GP',
            self::KwaZuluNatal => 'KZN',
            self::Limpopo => 'LP',
            self::Mpumalanga => 'MP',
            self::NorthernCape => 'NC',
            self::NorthWest => 'NW',
            self::WesternCape => 'WC',
        };
    }
}
