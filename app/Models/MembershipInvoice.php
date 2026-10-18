<?php

namespace App\Models;

use App\Enums\MembershipInvoicePaymentMethod;
use App\Enums\MembershipInvoiceStatus;
use Database\Factories\MembershipInvoiceFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A membership invoice is the money-side mirror of a {@see ClubMember}
 * renewal: it snapshots the fee for the member's category at the
 * moment of issue so a later price change does not retroactively
 * rewrite history. Status is kept in a string column (see
 * {@see MembershipInvoiceStatus}) so the ledger survives enum tweaks.
 */
#[Fillable([
    'organisation_id', 'club_member_id',
    'period_start', 'period_end', 'amount_cents',
    'status',
    'issued_at', 'due_at', 'paid_at', 'payment_pending_confirmation_at',
    'payment_method', 'paystack_reference',
    'issued_by', 'notes',
])]
class MembershipInvoice extends Model
{
    /** @use HasFactory<MembershipInvoiceFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'period_start' => 'immutable_date',
            'period_end' => 'immutable_date',
            'amount_cents' => 'integer',
            'status' => MembershipInvoiceStatus::class,
            'issued_at' => 'immutable_datetime',
            'due_at' => 'immutable_date',
            'paid_at' => 'immutable_datetime',
            'payment_pending_confirmation_at' => 'immutable_datetime',
            'payment_method' => MembershipInvoicePaymentMethod::class,
        ];
    }

    public function organisation(): BelongsTo
    {
        return $this->belongsTo(Organisation::class);
    }

    public function clubMember(): BelongsTo
    {
        return $this->belongsTo(ClubMember::class);
    }

    public function issuedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'issued_by');
    }

    /**
     * Invoices the member or secretary still owes money on.
     */
    public function scopeOutstanding(Builder $query): Builder
    {
        return $query->whereIn('status', MembershipInvoiceStatus::outstandingStates());
    }

    public function isOutstanding(): bool
    {
        return in_array($this->status, MembershipInvoiceStatus::outstandingStates(), true);
    }
}
