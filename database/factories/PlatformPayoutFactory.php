<?php

namespace Database\Factories;

use App\Models\Organisation;
use App\Models\PlatformPayout;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PlatformPayout>
 */
class PlatformPayoutFactory extends Factory
{
    public function definition(): array
    {
        return [
            'organisation_id' => Organisation::factory(),
            'subtotal_cents' => 10000,
            'admin_fee_cents' => 0,
            'admin_fee_vat_cents' => 0,
            'net_cents' => 10000,
            'status' => 'pending',
            'requested_at' => now(),
        ];
    }
}
