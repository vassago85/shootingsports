<?php

namespace App\Models;

use App\Enums\MembershipRole;
use App\Enums\MembershipStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Venue ↔ user relationship, same shape as {@see ProviderMembership}
 * so range ownership stacks cleanly onto the shooter account as a
 * capability instead of a one-shot `claimed_by` scalar.
 */
#[Fillable([
    'venue_id', 'user_id', 'role', 'status',
    'requested_at', 'approved_at', 'rejected_at', 'rejection_reason',
])]
class VenueMembership extends Model
{
    protected $table = 'venue_user';

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

    public function venue(): BelongsTo
    {
        return $this->belongsTo(Venue::class);
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
