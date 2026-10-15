<?php

namespace App\Filament\Desk\Resources\ClubMembers\Pages;

use App\Filament\Desk\Resources\ClubMembers\ClubMemberResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListClubMembers extends ListRecords
{
    protected static string $resource = ClubMemberResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()->label('Add member'),
        ];
    }
}
