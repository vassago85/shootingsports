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

    public function update(User $user, Venue $venue): bool
    {
        return $user->is_staff || $venue->claimed_by === $user->id;
    }

    public function delete(User $user, Venue $venue): bool
    {
        return $user->is_staff;
    }
}
