<?php

namespace App\Models;

use App\Enums\ClaimStatus;
use App\Enums\MembershipRole;
use App\Enums\MembershipStatus;
use App\Enums\OrganisationUserRole;
use App\Jobs\SendPushNotification;
use App\Mail\ListingClaimSubmittedMail;
use App\Support\RecordLinker;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

#[Fillable([
    'claimable_type', 'claimable_id', 'user_id', 'evidence',
    'status', 'reviewed_by', 'reviewed_at',
])]
class Claim extends Model
{
    protected function casts(): array
    {
        return [
            'status' => ClaimStatus::class,
            'reviewed_at' => 'immutable_datetime',
        ];
    }

    public function claimable(): MorphTo
    {
        return $this->morphTo();
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function approve(User $reviewer, OrganisationUserRole $role = OrganisationUserRole::Admin): void
    {
        $this->forceFill([
            'status' => ClaimStatus::Approved,
            'reviewed_by' => $reviewer->id,
            'reviewed_at' => now(),
        ])->save();

        $claimable = $this->claimable;

        if ($claimable && $claimable->isFillable('claimed_by')) {
            $claimable->forceFill(['claimed_by' => $this->user_id])->save();
        }

        if ($claimable instanceof Organisation) {
            OrganisationUser::query()->updateOrCreate(
                [
                    'organisation_id' => $claimable->id,
                    'user_id' => $this->user_id,
                ],
                [
                    'role' => $role,
                    'granted_by' => $reviewer->id,
                    'granted_at' => now(),
                    'status' => MembershipStatus::Approved->value,
                    'approved_at' => now(),
                ],
            );

            $claimant = $this->user;

            if ($claimant instanceof User && $claimant->is_match_director !== true) {
                $claimant->forceFill([
                    'is_match_director' => true,
                    'md_approved_at' => now(),
                    'md_rejected_at' => null,
                    'md_rejection_reason' => null,
                ])->save();
            }
        }

        if ($claimable instanceof Venue) {
            VenueMembership::query()->updateOrCreate(
                [
                    'venue_id' => $claimable->id,
                    'user_id' => $this->user_id,
                ],
                [
                    'role' => MembershipRole::Owner->value,
                    'status' => MembershipStatus::Approved->value,
                    'requested_at' => $this->created_at,
                    'approved_at' => now(),
                ],
            );
        }

        $this->notifyClaimant(approved: true);
    }

    public function reject(User $reviewer): void
    {
        $this->forceFill([
            'status' => ClaimStatus::Rejected,
            'reviewed_by' => $reviewer->id,
            'reviewed_at' => now(),
        ])->save();

        $this->notifyClaimant(approved: false);
    }

    /**
     * Push the approve/reject outcome to the claimant's mobile devices.
     * Counts as transactional — the user explicitly submitted the claim,
     * so this bypasses any marketing opt-out. Email continues to go via
     * {@see ListingClaimSubmittedMail} / its decision variant.
     */
    private function notifyClaimant(bool $approved): void
    {
        if ($this->user_id === null) {
            return;
        }

        $recordLabel = RecordLinker::label($this->claimable);
        $title = $approved ? 'Claim approved' : 'Claim rejected';
        $body = $approved
            ? sprintf('You now manage %s on the desk.', $recordLabel)
            : sprintf('Your claim on %s was rejected. Reply to the email for the reason.', $recordLabel);

        SendPushNotification::dispatch(
            userId: $this->user_id,
            title: $title,
            body: $body,
            data: [
                'type' => $approved ? 'claim.approved' : 'claim.rejected',
                'claim_id' => $this->id,
                'claimable_type' => $this->claimable_type,
                'claimable_id' => $this->claimable_id,
            ],
        );
    }
}
