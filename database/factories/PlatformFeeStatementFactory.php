<?php

namespace Database\Factories;

use App\Models\Organisation;
use App\Models\PlatformFeeStatement;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PlatformFeeStatement>
 */
class PlatformFeeStatementFactory extends Factory
{
    public function definition(): array
    {
        $periodStart = CarbonImmutable::now()->subMonthNoOverflow()->startOfMonth();

        return [
            'organisation_id' => Organisation::factory(),
            'period_start' => $periodStart->toDateString(),
            'period_end' => $periodStart->endOfMonth()->toDateString(),
            'total_fee_cents' => 0,
            'total_vat_cents' => 0,
            'total_gross_cents' => 0,
            'issued_at' => null,
        ];
    }
}
