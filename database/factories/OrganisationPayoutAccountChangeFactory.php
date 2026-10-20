<?php

namespace Database\Factories;

use App\Enums\PayoutStatus;
use App\Models\OrganisationPayoutAccount;
use App\Models\OrganisationPayoutAccountChange;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<OrganisationPayoutAccountChange>
 */
class OrganisationPayoutAccountChangeFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'organisation_payout_account_id' => OrganisationPayoutAccount::factory(),
            'user_id' => User::factory(),
            'old_last_four' => '6789',
            'new_last_four' => '4321',
            'old_bank_name' => 'ABSA Bank',
            'new_bank_name' => 'FNB',
            'old_account_holder' => 'Old Club',
            'new_account_holder' => 'New Club',
            'old_status' => PayoutStatus::Active,
            'new_status' => PayoutStatus::PendingApproval,
        ];
    }
}
