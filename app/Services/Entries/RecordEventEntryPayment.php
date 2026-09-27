<?php

namespace App\Services\Entries;

use App\Enums\EntryPaymentStatus;
use App\Enums\EntryStatus;
use App\Models\EventEntry;

/**
 * Marks a platform entry paid after Paystack verifies a one-off charge.
 * Does not touch Pro subscriptions.
 */
final class RecordEventEntryPayment
{
    /**
     * @param  array<string, mixed>  $verified
     */
    public function markPaid(array $verified): ?EventEntry
    {
        $reference = (string) ($verified['reference'] ?? '');

        if ($reference === '') {
            return null;
        }

        $entry = EventEntry::query()->where('payment_reference', $reference)->first();

        if (! $entry instanceof EventEntry) {
            return null;
        }

        if (($verified['status'] ?? '') !== 'success') {
            return $entry;
        }

        $entry->forceFill([
            'status' => EntryStatus::Entered,
            'payment_status' => EntryPaymentStatus::Paid,
            'paid_at' => $entry->paid_at ?? now(),
        ])->save();

        return $entry;
    }
}
