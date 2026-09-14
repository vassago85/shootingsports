<?php

namespace App\Policies;

use App\Enums\OrganisationUserRole;
use App\Models\Event;
use App\Models\User;

class EventPolicy
{
    public function viewAny(?User $user): bool
    {
        return true;
    }

    public function view(?User $user, Event $event): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        if ($user->is_staff) {
            return true;
        }

        return $user->organisationMemberships()
            ->whereIn('role', array_map(
                fn (OrganisationUserRole $role): string => $role->value,
                OrganisationUserRole::canManageEvents(),
            ))
            ->exists();
    }

    public function update(User $user, Event $event): bool
    {
        return $this->managesHost($user, $event);
    }

    public function delete(User $user, Event $event): bool
    {
        return $this->managesHost($user, $event);
    }

    public function restore(User $user, Event $event): bool
    {
        return $user->is_staff;
    }

    public function forceDelete(User $user, Event $event): bool
    {
        return $user->is_staff;
    }

    public function confirm(User $user, Event $event): bool
    {
        return $this->managesHost($user, $event);
    }

    private function managesHost(User $user, Event $event): bool
    {
        if ($user->is_staff) {
            return true;
        }

        $organisation = $event->hostOrganisation;

        if ($organisation === null) {
            return false;
        }

        return $user->holdsOrganisationRole($organisation, OrganisationUserRole::canManageEvents());
    }
}
