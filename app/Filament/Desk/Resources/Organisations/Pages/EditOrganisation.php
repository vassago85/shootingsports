<?php

namespace App\Filament\Desk\Resources\Organisations\Pages;

use App\Filament\Desk\Resources\Organisations\OrganisationResource;
use Filament\Resources\Pages\EditRecord;

class EditOrganisation extends EditRecord
{
    protected static string $resource = OrganisationResource::class;

    protected function getHeaderActions(): array
    {
        // Match directors cannot delete listings — staff handle that in /admin.
        return [];
    }
}
