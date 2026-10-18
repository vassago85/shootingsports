<?php

namespace Database\Factories;

use App\Models\AgencyAgreementAcceptance;
use App\Models\Organisation;
use App\Models\User;
use App\Services\Platform\AgencyAgreement;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AgencyAgreementAcceptance>
 */
class AgencyAgreementAcceptanceFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'organisation_id' => Organisation::factory(),
            'user_id' => User::factory(),
            'version' => AgencyAgreement::currentVersion(),
            'ip_address' => fake()->ipv4(),
        ];
    }
}
