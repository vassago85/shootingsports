<?php

namespace Database\Factories;

use App\Models\PlatformPayment;
use App\Models\PlatformSubscription;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PlatformPayment>
 */
class PlatformPaymentFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'platform_subscription_id' => PlatformSubscription::factory(),
            'paystack_reference' => 'ref_'.fake()->unique()->bothify('????########'),
            'amount_cents' => 11500,
            'currency' => 'ZAR',
            'paid_at' => now(),
        ];
    }
}
