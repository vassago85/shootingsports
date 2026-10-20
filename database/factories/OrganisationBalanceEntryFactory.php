<?php

namespace Database\Factories;

use App\Models\Organisation;
use App\Models\OrganisationBalanceEntry;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<OrganisationBalanceEntry>
 */
class OrganisationBalanceEntryFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'organisation_id' => Organisation::factory(),
            'payment_id' => null,
            'payment_refund_id' => null,
            'amount_cents' => 13850,
            'description' => 'Club share already settled, then refunded.',
        ];
    }
}
