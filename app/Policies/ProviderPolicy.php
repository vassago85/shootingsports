<?php

namespace App\Policies;

use App\Models\Provider;
use App\Models\User;

class ProviderPolicy
{
    public function viewAny(?User $user): bool
    {
        return true;
    }

    public function view(?User $user, Provider $provider): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return $user->is_staff;
    }

    public function update(User $user, Provider $provider): bool
    {
        return $user->is_staff || $provider->claimed_by === $user->id;
    }

    public function delete(User $user, Provider $provider): bool
    {
        return $user->is_staff;
    }
}
