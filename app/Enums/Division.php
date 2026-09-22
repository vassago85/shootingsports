<?php

namespace App\Enums;

use App\Enums\Concerns\HasLabelFromValue;
use Filament\Support\Contracts\HasLabel;

enum Division: string implements HasLabel
{
    use HasLabelFromValue;

    case Handgun = 'handgun';
    case BoltActionRifle = 'bolt-action-rifle';
    case SelfLoadingRifle = 'self-loading-rifle';
    case Shotgun = 'shotgun';
    case AirRifle = 'air-rifle';

    public function getLabel(): string
    {
        return match ($this) {
            self::Handgun => 'Handgun',
            self::BoltActionRifle => 'Bolt-action rifle',
            self::SelfLoadingRifle => 'Self-loading rifle',
            self::Shotgun => 'Shotgun',
            self::AirRifle => 'Air rifle',
        };
    }

    public function isPublic(): bool
    {
        return $this !== self::AirRifle;
    }

    /**
     * @return list<self>
     */
    public static function fromPublicQuery(?string $value): ?self
    {
        $division = self::tryFrom((string) $value);

        return $division?->isPublic() === true ? $division : null;
    }

    public static function publicCases(): array
    {
        return array_values(array_filter(
            self::cases(),
            fn (self $division): bool => $division->isPublic(),
        ));
    }

    public function legacyFamily(): DisciplineFamily
    {
        return match ($this) {
            self::Handgun => DisciplineFamily::Handgun,
            self::Shotgun => DisciplineFamily::Shotgun,
            self::AirRifle => DisciplineFamily::Airgun,
            self::BoltActionRifle, self::SelfLoadingRifle => DisciplineFamily::Rifle,
        };
    }
}
