<?php

namespace App\Models;

use Database\Factories\PlatformPayoutFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A batch of {@see ClubPayable} rows settled to a club by bank
 * transfer. Status flows pending → exported → paid (or cancelled).
 */
#[Fillable([
    'organisation_id', 'subtotal_cents', 'admin_fee_cents',
    'admin_fee_vat_cents', 'net_cents', 'status',
    'requested_at', 'exported_at', 'paid_at', 'bank_batch_id',
])]
class PlatformPayout extends Model
{
    /** @use HasFactory<PlatformPayoutFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'subtotal_cents' => 'integer',
            'admin_fee_cents' => 'integer',
            'admin_fee_vat_cents' => 'integer',
            'net_cents' => 'integer',
            'requested_at' => 'datetime',
            'exported_at' => 'datetime',
            'paid_at' => 'datetime',
        ];
    }

    public function organisation(): BelongsTo
    {
        return $this->belongsTo(Organisation::class);
    }

    public function payables(): HasMany
    {
        return $this->hasMany(ClubPayable::class);
    }
}
