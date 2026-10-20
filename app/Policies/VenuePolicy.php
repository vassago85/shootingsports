<?php

namespace App\Policies;

use App\Models\User;
use App\Models\Venue;

class VenuePolicy
{
    public function viewAny(?User $user): bool
    {
        return true;
    }

    public function view(?User $user, Venue $venue): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return $user->is_staff;
    }

    /**
     * Staff may edit any range. Everyone else needs either an approved
     * `venue_user` membership or the legacy `claimed_by` fallback —
     * the latter stays until the `claimed_by` shim is dropped in a
     * later clean-up migration.
     */
    public function update(User $user, Venue $venue): bool
    {
        if ($user->is_staff) {
            return true;
        }

        if ($venue->claimed_by === $user->id) {
            return true;
        }

        return $user->managesVenue($venue);
    }

    public function delete(User $user, Venue $venue): bool
    {
        return $user->is_staff;
    }
}
