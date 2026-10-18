<?php

namespace App\Models;

use Database\Factories\PlatformFeeLineFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One paid membership invoice's contribution to a
 * {@see PlatformFeeStatement}: gross collected, platform fee and the
 * VAT on that fee, all in integer cents.
 */
#[Fillable([
    'platform_fee_statement_id', 'membership_invoice_id',
    'gross_cents', 'fee_cents', 'fee_vat_cents', 'paid_at',
])]
class PlatformFeeLine extends Model
{
    /** @use HasFactory<PlatformFeeLineFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'gross_cents' => 'integer',
            'fee_cents' => 'integer',
            'fee_vat_cents' => 'integer',
            'paid_at' => 'datetime',
        ];
    }

    public function statement(): BelongsTo
    {
        return $this->belongsTo(PlatformFeeStatement::class, 'platform_fee_statement_id');
    }

    public function membershipInvoice(): BelongsTo
    {
        return $this->belongsTo(MembershipInvoice::class);
    }
}
