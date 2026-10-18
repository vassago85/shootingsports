<?php

namespace App\Models;

use App\Enums\BillingInterval;
use App\Enums\PlatformSubscriptionStatus;
use App\Enums\SubscriptionTier;
use Database\Factories\PlatformSubscriptionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'organisation_id',
    'tier',
    'interval',
    'status',
    'paystack_plan_code',
    'paystack_subscription_code',
    'paystack_customer_code',
    'paystack_reference',
    'paystack_email_token',
    'current_period_start',
    'current_period_end',
    'trial_ends_at',
    'cancel_at_period_end',
    'cancelled_at',
    'dunning_retry_count',
    'next_retry_on',
])]
class PlatformSubscription extends Model
{
    /** @use HasFactory<PlatformSubscriptionFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'tier' => SubscriptionTier::class,
            'interval' => BillingInterval::class,
            'status' => PlatformSubscriptionStatus::class,
            'current_period_start' => 'date',
            'current_period_end' => 'date',
            'trial_ends_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'cancel_at_period_end' => 'boolean',
            'next_retry_on' => 'date',
        ];
    }

    public function organisation(): BelongsTo
    {
        return $this->belongsTo(Organisation::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(PlatformPayment::class);
    }

    public static function startTrial(Organisation $organisation, SubscriptionTier $tier, BillingInterval $interval): self
    {
        return self::create([
            'organisation_id' => $organisation->id,
            'tier' => $tier,
            'interval' => $interval,
            'status' => PlatformSubscriptionStatus::Trial,
            'trial_ends_at' => now()->addDays((int) config('platform.trial_days', 30)),
        ]);
    }

    public function isReadOnly(): bool
    {
        return $this->status->isReadOnly();
    }
}
