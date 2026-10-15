<?php

namespace App\Policies;

use App\Models\ClubMember;
use App\Models\User;

class ClubMemberPolicy
{
    /**
     * Any user who manages a club's members can list members in Desk;
     * the resource's eloquent query scopes the actual records.
     */
    public function viewAny(User $user): bool
    {
        return $user->is_staff || $user->holdsAnyMemberManagingRole();
    }

    public function view(User $user, ClubMember $member): bool
    {
        return $user->canManageClubMembers($member->organisation);
    }

    public function create(User $user): bool
    {
        return $user->is_staff || $user->holdsAnyMemberManagingRole();
    }

    public function update(User $user, ClubMember $member): bool
    {
        return $user->canManageClubMembers($member->organisation);
    }

    /**
     * Ex-members are resigned, not deleted. Only staff may force-delete
     * a record, and the resource exposes no bulk-delete action.
     */
    public function delete(User $user, ClubMember $member): bool
    {
        return $user->is_staff;
    }

    public function restore(User $user, ClubMember $member): bool
    {
        return $user->is_staff;
    }

    public function forceDelete(User $user, ClubMember $member): bool
    {
        return $user->is_staff;
    }
}
