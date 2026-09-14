<?php

namespace App\Policies;

use App\Enums\OrganisationUserRole;
use App\Models\Organisation;
use App\Models\User;

class OrganisationPolicy
{
    public function viewAny(?User $user): bool
    {
        return true;
    }

    public function view(?User $user, Organisation $organisation): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return $user->is_staff;
    }

    public function update(User $user, Organisation $organisation): bool
    {
        if ($user->is_staff) {
            return true;
        }

        return $user->holdsOrganisationRole($organisation, [
            OrganisationUserRole::Admin,
            OrganisationUserRole::Editor,
        ]);
    }

    public function delete(User $user, Organisation $organisation): bool
    {
        return $user->is_staff;
    }
}
