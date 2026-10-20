<?php

namespace App\Filament\Desk\Resources\Venues\Pages;

use App\Filament\Desk\Resources\Venues\VenueResource;
use Filament\Resources\Pages\EditRecord;

class EditVenue extends EditRecord
{
    protected static string $resource = VenueResource::class;

    protected function getHeaderActions(): array
    {
        // Range owners cannot delete listings — staff handle that in
        // /admin, same as clubs and shops.
        return [];
    }
}
