<?php

namespace App\Filament\Desk\Resources\MembershipInvoices\Pages;

use App\Filament\Desk\Concerns\LocksUnpaidClubs;
use App\Filament\Desk\Resources\MembershipInvoices\MembershipInvoiceResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListMembershipInvoices extends ListRecords
{
    use LocksUnpaidClubs;

    protected static string $resource = MembershipInvoiceResource::class;

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
