<?php

namespace App\Filament\Desk\Resources\Placements\Pages;

use App\Filament\Desk\Resources\Placements\PlacementResource;
use Filament\Resources\Pages\ManageRecords;

class ManagePlacements extends ManageRecords
{
    protected static string $resource = PlacementResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
