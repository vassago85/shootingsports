<?php

namespace Database\Factories;

use App\Enums\PaymentStatus;
use App\Enums\PaystackFeeBearer;
use App\Enums\VatTreatment;
use App\Models\EventEntry;
use App\Models\Organisation;
use App\Models\Payment;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Payment>
 */
class PaymentFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'reference' => 'ss_'.fake()->unique()->bothify('########????'),
            'event_entry_id' => EventEntry::factory(),
            'organisation_id' => Organisation::factory(),
            'paystack_subaccount_code' => 'ACCT_testclub',
            'currency' => 'ZAR',
            'gross_amount_cents' => 15000,
            'platform_fee_cents' => 1000,
            'vat_on_fee_cents' => 150,
            'transaction_charge_cents' => 1150,
            'paystack_fee_cents' => null,
            'club_share_cents' => 13850,
            'fee_bearer' => PaystackFeeBearer::Account,
            'vat_treatment' => VatTreatment::Exclusive,
            'vat_rate_basis_points' => 1500,
            'status' => PaymentStatus::Pending,
            'raw_response' => null,
        ];
    }
}
