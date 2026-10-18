<?php

namespace App\Models;

use Database\Factories\PlatformPaymentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'platform_subscription_id',
    'paystack_reference',
    'amount_cents',
    'currency',
    'paid_at',
])]
class PlatformPayment extends Model
{
    /** @use HasFactory<PlatformPaymentFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'amount_cents' => 'integer',
            'paid_at' => 'datetime',
        ];
    }

    public function platformSubscription(): BelongsTo
    {
        return $this->belongsTo(PlatformSubscription::class);
    }
}
