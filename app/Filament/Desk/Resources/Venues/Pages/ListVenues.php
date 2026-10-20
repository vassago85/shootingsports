<?php

namespace App\Filament\Desk\Resources\Venues\Pages;

use App\Filament\Desk\Resources\Venues\VenueResource;
use Filament\Resources\Pages\ListRecords;

class ListVenues extends ListRecords
{
    protected static string $resource = VenueResource::class;

    protected function getHeaderActions(): array
    {
        // Range owners get here by claiming an existing listing on
        // the public /ranges directory; staff create fresh ranges in
        // /admin. No Create action from the desk, by design.
        return [];
    }
}
