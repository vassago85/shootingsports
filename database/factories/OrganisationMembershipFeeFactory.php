<?php

namespace Database\Factories;

use App\Enums\ClubMemberCategory;
use App\Models\Organisation;
use App\Models\OrganisationMembershipFee;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<OrganisationMembershipFee>
 */
class OrganisationMembershipFeeFactory extends Factory
{
    public function definition(): array
    {
        return [
            'organisation_id' => Organisation::factory(),
            'category' => ClubMemberCategory::Senior,
            'amount_cents' => fake()->numberBetween(20000, 80000),
            'active' => true,
        ];
    }

    public function senior(int $amountCents = 60000): static
    {
        return $this->state(fn (): array => [
            'category' => ClubMemberCategory::Senior,
            'amount_cents' => $amountCents,
        ]);
    }

    public function junior(int $amountCents = 25000): static
    {
        return $this->state(fn (): array => [
            'category' => ClubMemberCategory::Junior,
            'amount_cents' => $amountCents,
        ]);
    }
}
