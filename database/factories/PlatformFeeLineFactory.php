<?php

namespace Database\Factories;

use App\Models\MembershipInvoice;
use App\Models\PlatformFeeLine;
use App\Models\PlatformFeeStatement;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PlatformFeeLine>
 */
class PlatformFeeLineFactory extends Factory
{
    public function definition(): array
    {
        return [
            'platform_fee_statement_id' => PlatformFeeStatement::factory(),
            'membership_invoice_id' => MembershipInvoice::factory()->paid(),
            'gross_cents' => 60000,
            'fee_cents' => 1200,
            'fee_vat_cents' => 180,
            'paid_at' => now(),
        ];
    }
}
