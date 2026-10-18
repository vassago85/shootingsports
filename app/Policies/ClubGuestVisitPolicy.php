<?php

namespace App\Policies;

use App\Models\ClubGuestVisit;
use App\Models\User;

class ClubGuestVisitPolicy
{
    /**
     * Guest-day visibility and management is gated on the same
     * canManageClubMembers check as the ClubMember policy. Keeping
     * the two in step means secretaries don't need a separate seat
     * just to log a Saturday walk-in.
     */
    public function viewAny(User $user): bool
    {
        return $user->is_staff || $user->holdsAnyMemberManagingRole();
    }

    public function view(User $user, ClubGuestVisit $visit): bool
    {
        return $user->canManageClubMembers($visit->organisation);
    }

    public function create(User $user): bool
    {
        return $user->is_staff || $user->holdsAnyMemberManagingRole();
    }

    public function update(User $user, ClubGuestVisit $visit): bool
    {
        return $user->canManageClubMembers($visit->organisation);
    }

    public function delete(User $user, ClubGuestVisit $visit): bool
    {
        return $user->canManageClubMembers($visit->organisation);
    }
}
