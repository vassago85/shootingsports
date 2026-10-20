<?php

namespace App\Models;

use App\Enums\PaymentStatus;
use App\Enums\PaystackFeeBearer;
use App\Enums\VatTreatment;
use Database\Factories\PaymentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A match-entry charge collected by the platform and split to the
 * hosting club's Paystack subaccount. Membership invoices stay on
 * their own ledger.
 */
#[Fillable([
    'reference', 'event_entry_id', 'organisation_id', 'paystack_subaccount_code',
    'currency', 'gross_amount_cents', 'platform_fee_cents', 'vat_on_fee_cents',
    'transaction_charge_cents', 'paystack_fee_cents', 'club_share_cents',
    'fee_bearer', 'vat_treatment', 'vat_rate_basis_points',
    'status', 'paystack_transaction_id', 'raw_response', 'paid_at',
])]
#[Hidden(['raw_response'])]
class Payment extends Model
{
    /** @use HasFactory<PaymentFactory> */
    use HasFactory;

    protected $attributes = [
        'currency' => 'ZAR',
        'status' => 'pending',
    ];

    protected function casts(): array
    {
        return [
            'gross_amount_cents' => 'integer',
            'platform_fee_cents' => 'integer',
            'vat_on_fee_cents' => 'integer',
            'transaction_charge_cents' => 'integer',
            'paystack_fee_cents' => 'integer',
            'club_share_cents' => 'integer',
            'vat_rate_basis_points' => 'integer',
            'fee_bearer' => PaystackFeeBearer::class,
            'vat_treatment' => VatTreatment::class,
            'status' => PaymentStatus::class,
            'raw_response' => 'encrypted:array',
            'paid_at' => 'immutable_datetime',
        ];
    }

    public function eventEntry(): BelongsTo
    {
        return $this->belongsTo(EventEntry::class);
    }

    public function organisation(): BelongsTo
    {
        return $this->belongsTo(Organisation::class);
    }

    public function refunds(): HasMany
    {
        return $this->hasMany(PaymentRefund::class);
    }

    public function balanceEntries(): HasMany
    {
        return $this->hasMany(OrganisationBalanceEntry::class);
    }

    public function scopePaid(Builder $query): Builder
    {
        return $query->where('status', PaymentStatus::Paid);
    }
}
