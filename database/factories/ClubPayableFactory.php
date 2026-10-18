<?php

namespace Database\Factories;

use App\Models\ClubPayable;
use App\Models\Organisation;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ClubPayable>
 */
class ClubPayableFactory extends Factory
{
    public function definition(): array
    {
        return [
            'organisation_id' => Organisation::factory(),
            'membership_invoice_id' => null,
            'platform_payout_id' => null,
            'gross_cents' => 60000,
            'platform_fee_cents' => 1200,
            'platform_fee_vat_cents' => 180,
            'gateway_fee_cents' => 1000,
            'net_due_cents' => 57620,
            'paid_out' => false,
            'occurred_at' => now(),
        ];
    }

    public function paidOut(): static
    {
        return $this->state(fn (): array => ['paid_out' => true]);
    }
}
