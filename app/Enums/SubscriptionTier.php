<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum SubscriptionTier: string implements HasLabel
{
    case Micro = 'micro';
    case Standard = 'standard';
    case Large = 'large';

    public function getLabel(): string
    {
        return match ($this) {
            self::Micro => 'Micro',
            self::Standard => 'Standard',
            self::Large => 'Large',
        };
    }

    public function label(): string
    {
        return $this->getLabel();
    }

    public function priceCents(BillingInterval $interval): int
    {
        return (int) config("platform.tier_prices_cents.{$this->value}.{$interval->value}", 0);
    }

    public function memberCap(): int
    {
        return (int) config("platform.tier_member_caps.{$this->value}", 0);
    }

    public static function forMemberCount(int $count): self
    {
        return match (true) {
            $count <= self::Micro->memberCap() => self::Micro,
            $count <= self::Standard->memberCap() => self::Standard,
            default => self::Large,
        };
    }
}
