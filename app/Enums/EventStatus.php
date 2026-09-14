<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum EventStatus: string implements HasColor, HasLabel
{
    case Draft = 'draft';
    case Planned = 'planned';
    case Confirmed = 'confirmed';
    case EntriesOpen = 'entries_open';
    case Full = 'full';
    case Postponed = 'postponed';
    case Cancelled = 'cancelled';
    case Completed = 'completed';

    public function getLabel(): string
    {
        return match ($this) {
            self::Draft => 'Draft',
            self::Planned => 'Planned — date provisional',
            self::Confirmed => 'Confirmed',
            self::EntriesOpen => 'Entries open',
            self::Full => 'Full',
            self::Postponed => 'Postponed',
            self::Cancelled => 'Cancelled',
            self::Completed => 'Completed',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Draft => 'gray',
            self::Planned => 'gray',
            self::Confirmed => 'success',
            self::EntriesOpen => 'success',
            self::Full => 'warning',
            self::Postponed => 'warning',
            self::Cancelled => 'danger',
            self::Completed => 'info',
        };
    }

    public function isPublished(): bool
    {
        return $this !== self::Draft;
    }

    public function isConfirmedDate(): bool
    {
        return in_array($this, [self::Confirmed, self::EntriesOpen, self::Full], true);
    }

    public function pillClass(): string
    {
        return match ($this) {
            self::Draft => 'draft',
            self::Planned => 'planned',
            self::Confirmed => 'confirmed',
            self::EntriesOpen => 'open',
            self::Full => 'full',
            self::Postponed => 'postponed',
            self::Cancelled => 'cancelled',
            self::Completed => 'completed',
        };
    }
}
