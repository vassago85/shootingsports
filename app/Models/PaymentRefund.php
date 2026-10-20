<?php

namespace App\Models;

use App\Enums\PaymentRefundKind;
use App\Enums\PaymentRefundStatus;
use Database\Factories\PaymentRefundFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'payment_id', 'organisation_id', 'kind', 'status',
    'amount_cents', 'reason', 'paystack_refund_id',
    'club_share_settled', 'club_owes_cents', 'initiated_by', 'raw_response',
])]
#[Hidden(['raw_response'])]
class PaymentRefund extends Model
{
    /** @use HasFactory<PaymentRefundFactory> */
    use HasFactory;

    protected $attributes = [
        'status' => 'pending',
        'club_share_settled' => false,
        'club_owes_cents' => 0,
    ];

    protected function casts(): array
    {
        return [
            'kind' => PaymentRefundKind::class,
            'status' => PaymentRefundStatus::class,
            'amount_cents' => 'integer',
            'club_share_settled' => 'boolean',
            'club_owes_cents' => 'integer',
            'raw_response' => 'encrypted:array',
        ];
    }

    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }

    public function organisation(): BelongsTo
    {
        return $this->belongsTo(Organisation::class);
    }

    public function initiatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'initiated_by');
    }

    public function balanceEntries(): HasMany
    {
        return $this->hasMany(OrganisationBalanceEntry::class);
    }
}
