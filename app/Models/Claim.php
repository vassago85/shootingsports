<?php

namespace App\Models;

use App\Enums\ClaimStatus;
use App\Enums\OrganisationUserRole;
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
                ],
            );
        }
    }

    public function reject(User $reviewer): void
    {
        $this->forceFill([
            'status' => ClaimStatus::Rejected,
            'reviewed_by' => $reviewer->id,
            'reviewed_at' => now(),
        ])->save();
    }
}
