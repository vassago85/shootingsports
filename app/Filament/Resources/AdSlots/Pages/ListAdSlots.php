<?php

namespace App\Filament\Resources\AdSlots\Pages;

use App\Filament\Resources\AdSlots\AdSlotResource;
use Filament\Resources\Pages\ListRecords;

class ListAdSlots extends ListRecords
{
    protected static string $resource = AdSlotResource::class;

    protected function getHeaderActions(): array
    {
        return [
            \Filament\Actions\CreateAction::make(),
        ];
    }
}
