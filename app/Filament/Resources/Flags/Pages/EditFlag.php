<?php

namespace App\Filament\Resources\Flags\Pages;

use App\Filament\Resources\Flags\FlagResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditFlag extends EditRecord
{
    protected static string $resource = FlagResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
