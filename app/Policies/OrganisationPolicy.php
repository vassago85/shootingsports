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
        // Staff or any registered match director can create a draft listing.
        return true;
    }

    public function update(User $user, Organisation $organisation): bool
    {
        if ($user->is_staff) {
            return true;
        }

        return $user->holdsOrganisationRole($organisation, [
            OrganisationUserRole::MatchDirector,
            OrganisationUserRole::Admin,
            OrganisationUserRole::Editor,
        ]);
    }

    public function delete(User $user, Organisation $organisation): bool
    {
        return $user->is_staff;
    }

    public function restore(User $user, Organisation $organisation): bool
    {
        return $user->is_staff;
    }

    public function forceDelete(User $user, Organisation $organisation): bool
    {
        return $user->is_staff;
    }
}
