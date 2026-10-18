<?php

namespace App\Filament\Desk\Resources\EventEntries\Pages;

use App\Filament\Desk\Resources\EventEntries\EventEntryResource;
use Filament\Resources\Pages\ManageRecords;

class ManageEventEntries extends ManageRecords
{
    protected static string $resource = EventEntryResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
