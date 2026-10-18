<?php

namespace App\Services\Memberships;

use App\Enums\ClubMemberStatus;
use App\Models\ClubMember;
use App\Models\MembershipInvoice;
use App\Models\Organisation;
use App\Models\User;
use Carbon\CarbonImmutable;

/**
 * Bulk-issue the annual batch of membership invoices for one
 * organisation. Skips Resigned/Rejected members (no longer billable),
 * reuses already-issued invoices for the same period (idempotent)
 * and returns a tally so the caller can tell the secretary how many
 * invoices actually landed.
 */
class IssueAnnualMembershipInvoices
{
    public function __construct(private readonly IssueMembershipInvoice $issue) {}

    /**
     * @return array{created: int, skipped: int, invoices: list<MembershipInvoice>}
     */
    public function __invoke(
        Organisation $organisation,
        CarbonImmutable $periodStart,
        CarbonImmutable $periodEnd,
        ?User $issuedBy = null,
    ): array {
        $billable = ClubMember::query()
            ->where('organisation_id', $organisation->id)
            ->whereNotIn('status', [ClubMemberStatus::Resigned, ClubMemberStatus::Rejected])
            ->get();

        $created = 0;
        $skipped = 0;
        $invoices = [];

        foreach ($billable as $member) {
            $existed = MembershipInvoice::query()
                ->where('club_member_id', $member->id)
                ->whereDate('period_start', $periodStart->toDateString())
                ->whereDate('period_end', $periodEnd->toDateString())
                ->exists();

            $invoice = ($this->issue)($member, $periodStart, $periodEnd, $issuedBy);
            $invoices[] = $invoice;

            if ($existed) {
                $skipped++;
            } else {
                $created++;
            }
        }

        return [
            'created' => $created,
            'skipped' => $skipped,
            'invoices' => $invoices,
        ];
    }
}
