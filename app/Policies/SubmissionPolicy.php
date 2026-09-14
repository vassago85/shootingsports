<?php

namespace App\Policies;

use App\Models\Submission;
use App\Models\User;

class SubmissionPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->is_staff;
    }

    public function view(User $user, Submission $submission): bool
    {
        return $user->is_staff;
    }

    public function update(User $user, Submission $submission): bool
    {
        return $user->is_staff;
    }

    public function delete(User $user, Submission $submission): bool
    {
        return $user->is_staff;
    }
}
