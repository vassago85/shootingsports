<?php

namespace App\Filament\Desk\Concerns;

use App\Enums\OrganisationUserRole;
use App\Models\Organisation;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;

/**
 * Desk tools stay in the nav for a director of an unpaid club and open
 * a lock page instead of an empty table. A director of several clubs
 * is locked per club: paid clubs stay in the list.
 */
trait LocksUnpaidClubs
{
    /**
     * @return Collection<int, Organisation>
     */
    public function deskClubs(): Collection
    {
        $user = Auth::user();

        if (! $user instanceof User || $user->is_staff) {
            return collect();
        }

        return Organisation::query()
            ->whereIn('id', $this->deskClubIds($user))
            ->orderBy('name')
            ->get();
    }

    /**
     * @return Collection<int, Organisation>
     */
    public function unpaidDeskClubs(): Collection
    {
        return $this->deskClubs()->reject(fn (Organisation $club): bool => $club->isPaid())->values();
    }

    public function shouldLockClubTools(): bool
    {
        $clubs = $this->deskClubs();

        return $clubs->isNotEmpty() && $clubs->every(fn (Organisation $club): bool => ! $club->isPaid());
    }

    public function clubPlanSubheading(): ?string
    {
        if ($this->shouldLockClubTools()) {
            return null;
        }

        $names = $this->unpaidDeskClubs()->pluck('name');

        if ($names->isEmpty()) {
            return null;
        }

        return 'Not on the club subscription: '.$names->join(', ').'.';
    }

    public function clubPlanSummary(): string
    {
        return 'Members, membership invoices, guest days and membership reports are part of the club subscription.';
    }

    /**
     * @return list<int>
     */
    protected function deskClubIds(User $user): array
    {
        return $user->organisationMemberships()
            ->whereIn('role', array_map(
                fn (OrganisationUserRole $role): string => $role->value,
                OrganisationUserRole::canManageMembers(),
            ))
            ->pluck('organisation_id')
            ->all();
    }
}
