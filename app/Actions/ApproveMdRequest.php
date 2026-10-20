<?php

namespace App\Actions;

use App\Enums\EnquiryStatus;
use App\Enums\EnquiryType;
use App\Models\Enquiry;
use App\Models\User;
use App\Notifications\MdRequestApproved;

/**
 * Approve a match-director application.
 *
 * Single source of truth for the two call sites: the Users admin
 * table (where staff triage from the user list) and the Inbox
 * Director-requests tab (where staff triage from the queue).
 *
 * Both call sites expect:
 *   - `is_match_director` → true, `md_approved_at` stamped
 *   - a prior rejection cleared so the audit trail reads
 *     applied → rejected → approved cleanly
 *   - every open MD-signup enquiry owned by this user closed
 *   - MdRequestApproved notification sent to the applicant
 *
 * The action is intentionally idempotent on re-approval of a user
 * who already has `is_match_director=true` — it still stamps the
 * approval timestamp and emails, but staff should rarely trigger
 * that path because the row-action visibility rule hides the action
 * once approval is granted.
 */
class ApproveMdRequest
{
    public function __invoke(User $applicant): void
    {
        $applicant->forceFill([
            'is_match_director' => true,
            'md_approved_at' => now(),
            'md_rejected_at' => null,
            'md_rejection_reason' => null,
        ])->save();

        Enquiry::query()
            ->where('type', EnquiryType::MdSignup)
            ->where('user_id', $applicant->id)
            ->whereIn('status', [EnquiryStatus::New->value, EnquiryStatus::Read->value])
            ->update(['status' => EnquiryStatus::Closed->value]);

        $applicant->notify(new MdRequestApproved($applicant));
    }
}
