<?php

namespace App\Filament\Resources\Flags\Pages;

use App\Filament\Resources\Flags\FlagResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListFlags extends ListRecords
{
    protected static string $resource = FlagResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
