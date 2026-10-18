<?php

namespace App\Filament\Desk\Resources\ClubGuestVisits\Pages;

use App\Filament\Desk\Resources\ClubGuestVisits\ClubGuestVisitResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditClubGuestVisit extends EditRecord
{
    protected static string $resource = ClubGuestVisitResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
