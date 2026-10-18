<?php

namespace Database\Factories;

use App\Enums\BillingInterval;
use App\Enums\PlatformSubscriptionStatus;
use App\Enums\SubscriptionTier;
use App\Models\Organisation;
use App\Models\PlatformSubscription;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PlatformSubscription>
 */
class PlatformSubscriptionFactory extends Factory
{
    protected $model = PlatformSubscription::class;

    public function definition(): array
    {
        return [
            'organisation_id' => Organisation::factory(),
            'tier' => SubscriptionTier::Micro,
            'interval' => BillingInterval::Monthly,
            'status' => PlatformSubscriptionStatus::Active,
            'current_period_start' => now()->startOfMonth(),
            'current_period_end' => now()->endOfMonth(),
        ];
    }

    public function trial(): self
    {
        return $this->state(fn (): array => [
            'status' => PlatformSubscriptionStatus::Trial,
            'trial_ends_at' => now()->addDays(30),
            'current_period_start' => null,
            'current_period_end' => null,
        ]);
    }

    public function pastDue(): self
    {
        return $this->state(fn (): array => [
            'status' => PlatformSubscriptionStatus::PastDue,
            'next_retry_on' => now()->addDay(),
            'dunning_retry_count' => 1,
        ]);
    }
}
