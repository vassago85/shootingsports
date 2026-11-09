<?php

namespace App\Filament\Resources\MagazineIssues\Pages;

use App\Filament\Resources\MagazineIssues\MagazineIssueResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListMagazineIssues extends ListRecords
{
    protected static string $resource = MagazineIssueResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
