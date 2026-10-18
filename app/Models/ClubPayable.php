<?php

namespace App\Models;

use Database\Factories\ClubPayableFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * An amount the platform owes a club because a member's Paystack
 * payment landed in the platform account (club has no subaccount).
 * `net_due_cents` is gross less platform fee, fee VAT and gateway fee.
 */
#[Fillable([
    'organisation_id', 'membership_invoice_id', 'platform_payout_id',
    'gross_cents', 'platform_fee_cents', 'platform_fee_vat_cents',
    'gateway_fee_cents', 'net_due_cents', 'paid_out', 'occurred_at',
])]
class ClubPayable extends Model
{
    /** @use HasFactory<ClubPayableFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'gross_cents' => 'integer',
            'platform_fee_cents' => 'integer',
            'platform_fee_vat_cents' => 'integer',
            'gateway_fee_cents' => 'integer',
            'net_due_cents' => 'integer',
            'paid_out' => 'boolean',
            'occurred_at' => 'datetime',
        ];
    }

    public function organisation(): BelongsTo
    {
        return $this->belongsTo(Organisation::class);
    }

    public function membershipInvoice(): BelongsTo
    {
        return $this->belongsTo(MembershipInvoice::class);
    }

    public function platformPayout(): BelongsTo
    {
        return $this->belongsTo(PlatformPayout::class);
    }

    /**
     * @param  Builder<ClubPayable>  $query
     * @return Builder<ClubPayable>
     */
    public function scopePending(Builder $query): Builder
    {
        return $query->where('paid_out', false);
    }
}
