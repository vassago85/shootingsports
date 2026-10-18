<?php

namespace App\Services\Memberships;

use App\Enums\MembershipInvoicePaymentMethod;
use App\Enums\MembershipInvoiceStatus;
use App\Models\ClubMember;
use App\Models\MembershipInvoice;
use App\Models\User;
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
    ): MembershipInvoice {
        if ($invoice->status === MembershipInvoiceStatus::Paid) {
            return $invoice->fresh() ?? $invoice;
        }

        DB::transaction(function () use ($invoice, $method, $reference, $actor): void {
            $invoice->forceFill([
                'status' => MembershipInvoiceStatus::Paid,
                'paid_at' => now(),
                'payment_method' => $method,
                'paystack_reference' => $reference ?? $invoice->paystack_reference,
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
}
