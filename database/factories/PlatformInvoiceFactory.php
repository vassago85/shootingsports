<?php

namespace Database\Factories;

use App\Models\Organisation;
use App\Models\PlatformInvoice;
use App\Models\PlatformPayment;
use App\Models\PlatformSubscription;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PlatformInvoice>
 */
class PlatformInvoiceFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'organisation_id' => Organisation::factory(),
            'platform_subscription_id' => PlatformSubscription::factory(),
            'platform_payment_id' => PlatformPayment::factory(),
            'invoice_number' => 'INV-'.now()->year.'-'.str_pad((string) fake()->unique()->numberBetween(1, 999999), 6, '0', STR_PAD_LEFT),
            'issued_on' => today()->toDateString(),
            'period_start' => today()->toDateString(),
            'period_end' => today()->addMonth()->toDateString(),
            'description' => 'Micro tier — Monthly subscription ('.now()->format('Y-m').')',
            'amount_excl_vat_cents' => 10000,
            'vat_cents' => 1500,
            'amount_incl_vat_cents' => 11500,
            'vat_bps' => 1500,
            'currency' => 'ZAR',
            'paystack_reference' => 'ref_'.fake()->unique()->bothify('????########'),
        ];
    }

    /**
     * Attach the invoice to an existing payment and its subscription.
     */
    public function forPayment(PlatformPayment $payment): self
    {
        return $this->state(fn (): array => [
            'organisation_id' => $payment->platformSubscription->organisation_id,
            'platform_subscription_id' => $payment->platform_subscription_id,
            'platform_payment_id' => $payment->id,
            'paystack_reference' => $payment->paystack_reference,
            'amount_incl_vat_cents' => $payment->amount_cents,
        ]);
    }
}
