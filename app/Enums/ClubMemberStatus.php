<?php

namespace App\Enums;

use App\Enums\Concerns\HasLabelFromValue;
use App\Models\ClubMember;
use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

/**
 * Lifecycle state of a {@see ClubMember}. "Lapsed" is not a
 * stored state — it is derived from an {@see self::Active} record whose
 * expiry has passed and surfaced through
 * {@see ClubMember::effectiveStatus()}.
 */
enum ClubMemberStatus: string implements HasColor, HasLabel
{
    use HasLabelFromValue;

    case Applied = 'applied';
    case Active = 'active';
    case Lapsed = 'lapsed';
    case Suspended = 'suspended';
    case Resigned = 'resigned';
    case Rejected = 'rejected';

    public function getColor(): string
    {
        return match ($this) {
            self::Applied => 'warning',
            self::Active => 'success',
            self::Lapsed => 'gray',
            self::Suspended => 'danger',
            self::Resigned => 'gray',
            self::Rejected => 'danger',
        };
    }

    /**
     * Statuses that count as "ex-member" — kept for history, hidden
     * from day-to-day filters by default.
     *
     * @return list<self>
     */
    public static function exMemberStates(): array
    {
        return [self::Resigned, self::Rejected];
    }
}
