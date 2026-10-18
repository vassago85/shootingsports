<?php

namespace App\Services\Memberships;

use App\Enums\MembershipInvoicePaymentMethod;
use App\Enums\MembershipInvoiceStatus;
use App\Models\ClubMember;
use App\Models\MembershipInvoice;
use App\Models\User;
use App\Support\PlatformFees;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Mark a membership invoice paid and renew the underlying
 * {@see ClubMember} to the invoice's `period_end` so
 * the member row stays truthful.
 *
 * Idempotent: calling again on a Paid invoice returns the invoice
 * untouched (reconciliation and Paystack webhook retries are safe).
 */
class RecordMembershipPayment
{
    public function __invoke(
        MembershipInvoice $invoice,
        MembershipInvoicePaymentMethod $method,
        ?string $reference = null,
        ?User $actor = null,
        ?int $gatewayFeeCents = null,
    ): MembershipInvoice {
        if ($invoice->status === MembershipInvoiceStatus::Paid) {
            return $invoice->fresh() ?? $invoice;
        }

        DB::transaction(function () use ($invoice, $method, $reference, $actor, $gatewayFeeCents): void {
            $invoice->forceFill([
                'status' => MembershipInvoiceStatus::Paid,
                'paid_at' => now(),
                'payment_method' => $method,
                'paystack_reference' => $reference ?? $invoice->paystack_reference,
                ...$this->feeBreakdown($invoice, $gatewayFeeCents),
            ])->save();

            $member = $invoice->clubMember()->first();
            if ($member === null) {
                return;
            }

            $periodEnd = CarbonImmutable::parse($invoice->period_end->toDateString());

            // The invoice is the money side of a renewal, so the
            // member row should move with it. Keeps secretaries from
            // having to double-touch Members every time an EFT lands.
            $member->renew($periodEnd, actor: $actor, reason: 'Invoice '.$invoice->id.' paid');
        });

        return $invoice->fresh() ?? $invoice;
    }

    /**
     * Fee columns for a gateway-settled payment. Passing no gateway fee
     * (EFT, manual reconciliation) leaves the columns untouched. The
     * platform fee is only recorded when the club has a Paystack
     * subaccount, because that is the only flow where it is actually
     * split off at source.
     *
     * @return array<string, int>
     */
    private function feeBreakdown(MembershipInvoice $invoice, ?int $gatewayFeeCents): array
    {
        if ($gatewayFeeCents === null) {
            return [];
        }

        $amount = (int) $invoice->amount_cents;
        $hasSubaccount = $invoice->organisation?->paystack_subaccount_id !== null;
        $platformFee = $hasSubaccount ? PlatformFees::feeCents($amount) : 0;
        $platformFeeVat = $hasSubaccount ? PlatformFees::feeVatCents($platformFee) : 0;

        return [
            'gateway_fee_cents' => $gatewayFeeCents,
            'platform_fee_cents' => $platformFee,
            'platform_fee_vat_cents' => $platformFeeVat,
            'net_to_club_cents' => max(0, $amount - $gatewayFeeCents - $platformFee - $platformFeeVat),
        ];
    }
}
