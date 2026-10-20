<?php

namespace App\Support\Inbox;

use Carbon\CarbonInterface;

/**
 * Compact "age in days" label used by every Inbox tab column (and the
 * Articles tab's purpose-built table). Centralised so the Admin Inbox,
 * each resource's own list view, and tests all agree on exactly how
 * "today" vs "1 day" vs "N days" is spelled.
 */
final class AgeLabel
{
    public static function for(?CarbonInterface $when): string
    {
        if ($when === null) {
            return '—';
        }

        $days = (int) $when->diffInDays(now());

        return match (true) {
            $days === 0 => 'today',
            $days === 1 => '1 day',
            default => "{$days} days",
        };
    }
}
