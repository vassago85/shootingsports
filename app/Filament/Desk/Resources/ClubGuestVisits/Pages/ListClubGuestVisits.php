<?php

namespace App\Filament\Desk\Resources\ClubGuestVisits\Pages;

use App\Filament\Desk\Concerns\LocksUnpaidClubs;
use App\Filament\Desk\Resources\ClubGuestVisits\ClubGuestVisitResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListClubGuestVisits extends ListRecords
{
    use LocksUnpaidClubs;

    protected static string $resource = ClubGuestVisitResource::class;

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

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
