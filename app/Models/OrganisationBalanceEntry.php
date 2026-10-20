<?php

namespace App\Models;

use Database\Factories\OrganisationBalanceEntryFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Ledger line for money a club owes the platform after a settled
 * share is refunded or charged back. Positive amount_cents means
 * the club owes the platform.
 */
#[Fillable([
    'organisation_id', 'payment_id', 'payment_refund_id',
    'amount_cents', 'description',
])]
class OrganisationBalanceEntry extends Model
{
    /** @use HasFactory<OrganisationBalanceEntryFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'amount_cents' => 'integer',
        ];
    }

    public function organisation(): BelongsTo
    {
        return $this->belongsTo(Organisation::class);
    }

    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }

    public function paymentRefund(): BelongsTo
    {
        return $this->belongsTo(PaymentRefund::class);
    }
}
