<?php

namespace App\Policies;

use App\Models\Media;
use App\Models\User;

class MediaPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->is_staff;
    }

    public function view(User $user, Media $media): bool
    {
        return $user->is_staff;
    }

    public function update(User $user, Media $media): bool
    {
        return $user->is_staff;
    }

    public function delete(User $user, Media $media): bool
    {
        return $user->is_staff;
    }
}
