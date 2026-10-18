<?php

namespace App\Models;

use Database\Factories\PlatformFeeStatementFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A monthly ledger statement of the platform fees charged on one
 * organisation's paid membership invoices. One row per
 * (organisation, period_start, period_end); totals are integer cents
 * and are the evidence handed to the accountant for VAT 201.
 */
#[Fillable([
    'organisation_id', 'period_start', 'period_end',
    'total_fee_cents', 'total_vat_cents', 'total_gross_cents',
    'issued_at',
])]
class PlatformFeeStatement extends Model
{
    /** @use HasFactory<PlatformFeeStatementFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'period_start' => 'date:Y-m-d',
            'period_end' => 'date:Y-m-d',
            'total_fee_cents' => 'integer',
            'total_vat_cents' => 'integer',
            'total_gross_cents' => 'integer',
            'issued_at' => 'datetime',
        ];
    }

    public function organisation(): BelongsTo
    {
        return $this->belongsTo(Organisation::class);
    }

    public function lines(): HasMany
    {
        return $this->hasMany(PlatformFeeLine::class);
    }
}
