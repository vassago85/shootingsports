<?php

namespace App\Policies;

use App\Models\MembershipInvoice;
use App\Models\User;

class MembershipInvoicePolicy
{
    /**
     * Mirrors ClubMemberPolicy — anyone who can manage members in a
     * club can see and manage that club's invoice ledger. Resource
     * query scopes the actual visibility.
     */
    public function viewAny(User $user): bool
    {
        return $user->is_staff || $user->holdsAnyMemberManagingRole();
    }

    public function view(User $user, MembershipInvoice $invoice): bool
    {
        return $user->canManageClubMembers($invoice->organisation);
    }

    public function create(User $user): bool
    {
        return $user->is_staff || $user->holdsAnyMemberManagingRole();
    }

    public function update(User $user, MembershipInvoice $invoice): bool
    {
        return $user->canManageClubMembers($invoice->organisation);
    }

    public function delete(User $user, MembershipInvoice $invoice): bool
    {
        return $user->is_staff;
    }
}
