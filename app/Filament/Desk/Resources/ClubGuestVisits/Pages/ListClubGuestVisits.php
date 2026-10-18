<?php

namespace App\Filament\Desk\Resources\ClubGuestVisits\Pages;

use App\Filament\Desk\Resources\ClubGuestVisits\ClubGuestVisitResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListClubGuestVisits extends ListRecords
{
    protected static string $resource = ClubGuestVisitResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
