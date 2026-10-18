<?php

namespace App\Models;

use App\Enums\MembershipStatus;
use App\Enums\OrganisationUserRole;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\Pivot;

#[Fillable([
    'organisation_id', 'user_id', 'role',
    'granted_by', 'granted_at',
    'status', 'requested_at', 'approved_at', 'rejected_at', 'rejection_reason',
])]
class OrganisationUser extends Pivot
{
    protected $table = 'organisation_user';

    public $incrementing = true;

    protected function casts(): array
    {
        return [
            'role' => OrganisationUserRole::class,
            'granted_at' => 'immutable_datetime',
            'status' => MembershipStatus::class,
            'requested_at' => 'immutable_datetime',
            'approved_at' => 'immutable_datetime',
            'rejected_at' => 'immutable_datetime',
        ];
    }

    public function organisation(): BelongsTo
    {
        return $this->belongsTo(Organisation::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function grantedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'granted_by');
    }

    public function isApproved(): bool
    {
        return $this->status === MembershipStatus::Approved;
    }

    public function isPending(): bool
    {
        return $this->status === MembershipStatus::Pending;
    }

    public function isRejected(): bool
    {
        return $this->status === MembershipStatus::Rejected;
    }
}
