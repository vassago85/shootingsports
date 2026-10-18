<?php

namespace App\Services\Memberships;

use App\Enums\MembershipInvoiceStatus;
use App\Models\ClubMember;
use App\Models\MembershipInvoice;
use App\Models\OrganisationMembershipFee;
use App\Models\User;
use Carbon\CarbonImmutable;
use RuntimeException;

/**
 * Issue one membership invoice for a {@see ClubMember}. The amount
 * snapshot is read from {@see OrganisationMembershipFee} for the
 * member's category so a later price change does not retroactively
 * rewrite an open invoice.
 *
 * Idempotent per `(club_member_id, period_start, period_end)`: a
 * second call for the same window returns the existing invoice.
 */
class IssueMembershipInvoice
{
    public function __invoke(
        ClubMember $member,
        CarbonImmutable $periodStart,
        CarbonImmutable $periodEnd,
        ?User $issuedBy = null,
        ?int $amountCentsOverride = null,
    ): MembershipInvoice {
        $existing = MembershipInvoice::query()
            ->where('club_member_id', $member->id)
            ->whereDate('period_start', $periodStart->toDateString())
            ->whereDate('period_end', $periodEnd->toDateString())
            ->first();

        if ($existing !== null) {
            return $existing;
        }

        $amountCents = $amountCentsOverride ?? $this->lookupFeeCents($member);

        return MembershipInvoice::create([
            'organisation_id' => $member->organisation_id,
            'club_member_id' => $member->id,
            'period_start' => $periodStart->toDateString(),
            'period_end' => $periodEnd->toDateString(),
            'amount_cents' => $amountCents,
            'status' => MembershipInvoiceStatus::Issued,
            'issued_at' => now(),
            'due_at' => $periodEnd->toDateString(),
            'issued_by' => $issuedBy?->id,
        ]);
    }

    private function lookupFeeCents(ClubMember $member): int
    {
        $fee = OrganisationMembershipFee::query()
            ->where('organisation_id', $member->organisation_id)
            ->where('category', $member->category->value)
            ->where('active', true)
            ->first();

        if ($fee === null) {
            throw new RuntimeException(sprintf(
                'No active fee configured for %s (%s) in organisation %d.',
                $member->fullName(),
                $member->category->value,
                $member->organisation_id,
            ));
        }

        return $fee->amount_cents;
    }
}
