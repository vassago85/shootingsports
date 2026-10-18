<?php

namespace App\Enums;

use App\Enums\Concerns\HasLabelFromValue;
use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

/**
 * Lifecycle of a membership invoice.
 *
 * - Draft: created but not yet shown to the member.
 * - Issued: visible to the member; dues clock is ticking.
 * - Paid: payment recorded (any method).
 * - Void: cancelled before any payment (bad data, duplicate, etc.).
 * - Waived: no payment required (life member, bursary, etc.).
 */
enum MembershipInvoiceStatus: string implements HasColor, HasLabel
{
    use HasLabelFromValue;

    case Draft = 'draft';
    case Issued = 'issued';
    case Paid = 'paid';
    case Void = 'void';
    case Waived = 'waived';

    public function getColor(): string
    {
        return match ($this) {
            self::Draft => 'gray',
            self::Issued => 'warning',
            self::Paid => 'success',
            self::Void => 'gray',
            self::Waived => 'info',
        };
    }

    /**
     * Statuses that still owe money.
     *
     * @return list<self>
     */
    public static function outstandingStates(): array
    {
        return [self::Issued];
    }
}
