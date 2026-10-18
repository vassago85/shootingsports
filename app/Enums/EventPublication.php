<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

/**
 * Phase B — publication state of an event, separate from its
 * `EventStatus` (draft / confirmed / entries open / cancelled etc).
 * `status` describes what is happening on the day; `publication`
 * describes whether the event is on the public calendar.
 *
 *   draft           — director is writing, staff ignores
 *   pending_review  — director has asked staff to publish
 *   published       — on the public calendar
 *   rejected        — staff said no; director sees the reason
 */
enum EventPublication: string implements HasColor, HasLabel
{
    case Draft = 'draft';
    case PendingReview = 'pending_review';
    case Published = 'published';
    case Rejected = 'rejected';

    public function getLabel(): string
    {
        return match ($this) {
            self::Draft => 'Draft',
            self::PendingReview => 'Pending review',
            self::Published => 'Published',
            self::Rejected => 'Rejected',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Draft => 'gray',
            self::PendingReview => 'warning',
            self::Published => 'success',
            self::Rejected => 'danger',
        };
    }
}
