<?php

namespace App\Filament\Resources\Placements\Pages;

use App\Filament\Resources\AdSlots\AdSlotResource;
use App\Filament\Resources\Placements\PlacementResource;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Filament\Support\Icons\Heroicon;

class ListPlacements extends ListRecords
{
    protected static string $resource = PlacementResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('adSlotCatalog')
                ->label('Ad slot catalog')
                ->icon(Heroicon::OutlinedRectangleGroup)
                ->color('gray')
                ->url(AdSlotResource::getUrl('index')),
            CreateAction::make(),
        ];
    }
}
