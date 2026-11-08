<?php

namespace App\Filament\Desk\Resources\EventEntries\Pages;

use App\Enums\OrganisationUserRole;
use App\Filament\Desk\Concerns\LocksUnpaidClubs;
use App\Filament\Desk\Resources\EventEntries\EventEntryResource;
use App\Models\User;
use Filament\Resources\Pages\ManageRecords;

class ManageEventEntries extends ManageRecords
{
    use LocksUnpaidClubs;

    protected static string $resource = EventEntryResource::class;

    public function mount(): void
    {
        parent::mount();

        if ($this->shouldLockClubTools()) {
            $this->view = 'filament.desk.pages.club-plan-locked';
        }
    }

    public function getSubheading(): ?string
    {
        return $this->clubPlanSubheading();
    }

    public function clubPlanSummary(): string
    {
        return 'On-site entries and this entries list are part of the club subscription.';
    }

    /**
     * @return list<int>
     */
    protected function deskClubIds(User $user): array
    {
        return $user->organisationMemberships()
            ->whereIn('role', array_map(
                fn (OrganisationUserRole $role): string => $role->value,
                OrganisationUserRole::canManageEvents(),
            ))
            ->pluck('organisation_id')
            ->all();
    }

    protected function getHeaderActions(): array
    {
        return [];
    }
}
