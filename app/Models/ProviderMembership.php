<?php

namespace App\Models;

use App\Enums\MembershipRole;
use App\Enums\MembershipStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Phase B — provider ↔ user relationship. Same shape as
 * {@see OrganisationMembership} so the desk and policy code can
 * share a trait across both.
 */
#[Fillable([
    'provider_id', 'user_id', 'role', 'status',
    'requested_at', 'approved_at', 'rejected_at', 'rejection_reason',
])]
class ProviderMembership extends Model
{
    protected $table = 'provider_user';

    protected function casts(): array
    {
        return [
            'role' => MembershipRole::class,
            'status' => MembershipStatus::class,
            'requested_at' => 'immutable_datetime',
            'approved_at' => 'immutable_datetime',
            'rejected_at' => 'immutable_datetime',
        ];
    }

    public function provider(): BelongsTo
    {
        return $this->belongsTo(Provider::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isApproved(): bool
    {
        return $this->status === MembershipStatus::Approved;
    }

    public function isOwner(): bool
    {
        return $this->role === MembershipRole::Owner;
    }
}
