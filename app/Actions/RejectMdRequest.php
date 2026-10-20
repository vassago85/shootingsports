<?php

namespace App\Actions;

use App\Enums\EnquiryStatus;
use App\Enums\EnquiryType;
use App\Models\Enquiry;
use App\Models\User;
use App\Notifications\MdRequestRejected;

/**
 * Reject a match-director application.
 *
 * Shared between the Users admin table and the Inbox Director-requests
 * tab. Applicants keep their shooter account; the only outcomes are:
 *   - `is_match_director` cleared (if somehow true), rejection stamp set
 *   - the applicant-visible reason stored on the user
 *   - every open MD-signup enquiry owned by this user closed
 *   - MdRequestRejected notification sent (includes the reason)
 *
 * The reason is optional — if the caller passes null or an empty
 * string, the notification omits the "we rejected because…" line
 * entirely.
 */
class RejectMdRequest
{
    public function __invoke(User $applicant, ?string $reason = null): void
    {
        $normalisedReason = $reason !== null ? trim($reason) : '';
        $storedReason = $normalisedReason !== '' ? $normalisedReason : null;

        $applicant->forceFill([
            'is_match_director' => false,
            'md_rejected_at' => now(),
            'md_rejection_reason' => $storedReason,
        ])->save();

        Enquiry::query()
            ->where('type', EnquiryType::MdSignup)
            ->where('user_id', $applicant->id)
            ->whereIn('status', [EnquiryStatus::New->value, EnquiryStatus::Read->value])
            ->update(['status' => EnquiryStatus::Closed->value]);

        $applicant->notify(new MdRequestRejected($applicant, $storedReason));
    }
}
