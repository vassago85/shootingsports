<?php

namespace Database\Factories;

use App\Enums\PayoutAccountType;
use App\Enums\PayoutDocumentType;
use App\Enums\PayoutStatus;
use App\Models\Organisation;
use App\Models\OrganisationPayoutAccount;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<OrganisationPayoutAccount>
 */
class OrganisationPayoutAccountFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'organisation_id' => Organisation::factory(),
            'status' => PayoutStatus::Draft,
            'paystack_subaccount_code' => null,
            'account_holder_name' => fake()->company(),
            'bank_code' => '632005',
            'bank_name' => 'ABSA Bank',
            'bank_enabled_for_verification' => true,
            'account_number' => '0123456789',
            'account_type' => PayoutAccountType::Business,
            'document_type' => PayoutDocumentType::BusinessRegistrationNumber,
            'document_number' => '2012/123456/07',
        ];
    }

    public function active(): static
    {
        return $this->state(fn (): array => [
            'status' => PayoutStatus::Active,
            'paystack_subaccount_code' => 'ACCT_'.fake()->unique()->bothify('????????'),
            'terms_accepted_at' => now(),
            'approved_at' => now(),
            'submitted_at' => now()->subDay(),
        ]);
    }

    public function pendingApproval(): static
    {
        return $this->state(fn (): array => [
            'status' => PayoutStatus::PendingApproval,
            'paystack_subaccount_code' => 'ACCT_'.fake()->unique()->bothify('????????'),
            'terms_accepted_at' => now(),
            'submitted_at' => now(),
        ]);
    }
}
