<?php

namespace App\Models;

use Database\Factories\PlatformInvoiceFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'organisation_id',
    'platform_subscription_id',
    'platform_payment_id',
    'invoice_number',
    'issued_on',
    'period_start',
    'period_end',
    'description',
    'amount_excl_vat_cents',
    'vat_cents',
    'amount_incl_vat_cents',
    'vat_bps',
    'currency',
    'paystack_reference',
])]
class PlatformInvoice extends Model
{
    /** @use HasFactory<PlatformInvoiceFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'issued_on' => 'date:Y-m-d',
            'period_start' => 'date:Y-m-d',
            'period_end' => 'date:Y-m-d',
            'amount_excl_vat_cents' => 'integer',
            'vat_cents' => 'integer',
            'amount_incl_vat_cents' => 'integer',
            'vat_bps' => 'integer',
        ];
    }

    public function organisation(): BelongsTo
    {
        return $this->belongsTo(Organisation::class);
    }

    public function platformSubscription(): BelongsTo
    {
        return $this->belongsTo(PlatformSubscription::class);
    }

    public function platformPayment(): BelongsTo
    {
        return $this->belongsTo(PlatformPayment::class);
    }
}
