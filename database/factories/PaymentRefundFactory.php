<?php

namespace Database\Factories;

use App\Enums\PaymentRefundKind;
use App\Enums\PaymentRefundStatus;
use App\Models\Organisation;
use App\Models\Payment;
use App\Models\PaymentRefund;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PaymentRefund>
 */
class PaymentRefundFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'payment_id' => Payment::factory(),
            'organisation_id' => Organisation::factory(),
            'kind' => PaymentRefundKind::Refund,
            'status' => PaymentRefundStatus::Pending,
            'amount_cents' => 15000,
            'reason' => 'Shooter withdrew before the match.',
            'club_share_settled' => false,
            'club_owes_cents' => 0,
        ];
    }
}
