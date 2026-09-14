<?php

namespace App\Policies;

use App\Models\Claim;
use App\Models\User;

class ClaimPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->is_staff;
    }

    public function view(User $user, Claim $claim): bool
    {
        return $user->is_staff;
    }

    public function update(User $user, Claim $claim): bool
    {
        return $user->is_staff;
    }
}
