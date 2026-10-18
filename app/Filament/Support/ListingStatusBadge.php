<?php

namespace App\Filament\Support;

use App\Enums\ListingStatus;
use App\Enums\VerificationState;
use App\Models\Concerns\HasVerification;
use Filament\Tables\Columns\TextColumn;

/**
 * Combined "Status · Verification" badge for the Clubs / Ranges /
 * Industry list pages.
 *
 * Phase 2 showed `status` and `verification_state` as two separate
 * badges on every row, which read as two green pills side by side.
 * The audit asked for one combined badge in a clear priority order:
 * Draft > Pending > Archived > Published (unconfirmed|verified|ageing).
 *
 * The underlying columns stay in the database and in the record forms
 * — only the list rendering collapses to one visible badge.
 */
class ListingStatusBadge
{
    /**
     * Factory for the combined column. Pass the attribute name that
     * holds the ListingStatus (always "status" on current models).
     */
    public static function make(string $name = 'status'): TextColumn
    {
        return TextColumn::make($name)
            ->label('Status')
            ->badge()
            ->sortable()
            ->state(fn (object $record): string => self::label($record))
            ->color(fn (string $state): string => self::color($state));
    }

    /**
     * One-line label like "Draft", "Pending", "Published · Verified".
     */
    public static function label(object $record): string
    {
        $status = $record->status instanceof ListingStatus
            ? $record->status
            : ListingStatus::tryFrom((string) ($record->status ?? ''));

        if ($status === null) {
            return 'Unknown';
        }

        // Draft / Pending / Archived say everything on their own —
        // the verification layer only matters once a record is
        // Published and visible to shooters.
        if ($status !== ListingStatus::Published) {
            return $status->getLabel();
        }

        if (! self::hasVerification($record)) {
            return 'Published';
        }

        $verification = $record->verification_state instanceof VerificationState
            ? $record->verification_state
            : VerificationState::tryFrom((string) ($record->verification_state ?? ''));

        if ($verification === null) {
            return 'Published';
        }

        return 'Published · '.$verification->getLabel();
    }

    private static function color(string $state): string
    {
        return match (true) {
            $state === 'Published · Verified' => 'success',
            $state === 'Published · Ageing' => 'warning',
            $state === 'Published · Unconfirmed' => 'warning',
            $state === 'Published · Archived' => 'danger',
            $state === 'Published' => 'success',
            $state === 'Pending' => 'warning',
            $state === 'Archived' => 'danger',
            $state === 'Draft' => 'gray',
            default => 'gray',
        };
    }

    private static function hasVerification(object $record): bool
    {
        return in_array(HasVerification::class, class_uses_recursive($record), true);
    }
}
