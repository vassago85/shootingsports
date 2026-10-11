<?php

namespace App\Enums;

use Carbon\CarbonInterface;
use Filament\Support\Contracts\HasLabel;

enum ImportFrequency: string implements HasLabel
{
    case Off = 'off';
    case Daily = 'daily';
    case Weekly = 'weekly';
    case Monthly = 'monthly';

    public function getLabel(): string
    {
        return match ($this) {
            self::Off => 'Off',
            self::Daily => 'Every day',
            self::Weekly => 'Every Monday',
            self::Monthly => '1st of each month',
        };
    }

    public function isDueOn(CarbonInterface $day): bool
    {
        return match ($this) {
            self::Off => false,
            self::Daily => true,
            self::Weekly => $day->isMonday(),
            self::Monthly => $day->day === 1,
        };
    }

    /**
     * Days after the last run before staff are told the scheduler has
     * stopped, allowing one missed day on top of the interval.
     */
    public function staleAfterDays(): ?int
    {
        return match ($this) {
            self::Off => null,
            self::Daily => 2,
            self::Weekly => 8,
            self::Monthly => 32,
        };
    }
}
