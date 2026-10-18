<?php

namespace Database\Factories;

use App\Enums\MembershipInvoiceStatus;
use App\Models\ClubMember;
use App\Models\MembershipInvoice;
use App\Models\Organisation;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MembershipInvoice>
 */
class MembershipInvoiceFactory extends Factory
{
    public function definition(): array
    {
        $periodStart = CarbonImmutable::create((int) CarbonImmutable::today()->year, 1, 1);
        $periodEnd = CarbonImmutable::create((int) CarbonImmutable::today()->year, 12, 31);

        return [
            'organisation_id' => Organisation::factory(),
            'club_member_id' => ClubMember::factory(),
            'period_start' => $periodStart->toDateString(),
            'period_end' => $periodEnd->toDateString(),
            'amount_cents' => 60000,
            'status' => MembershipInvoiceStatus::Issued,
            'issued_at' => now(),
            'due_at' => now()->addDays(30),
            'paid_at' => null,
            'payment_method' => null,
            'paystack_reference' => null,
            'issued_by' => null,
            'notes' => null,
        ];
    }

    public function draft(): static
    {
        return $this->state(fn (): array => [
            'status' => MembershipInvoiceStatus::Draft,
            'issued_at' => null,
        ]);
    }

    public function paid(): static
    {
        return $this->state(fn (): array => [
            'status' => MembershipInvoiceStatus::Paid,
            'paid_at' => now(),
        ]);
    }
}
