<?php

namespace App\Filament\Desk\Resources\Enquiries\Pages;

use App\Filament\Desk\Resources\Enquiries\EnquiryResource;
use Filament\Resources\Pages\ManageRecords;

class ManageEnquiries extends ManageRecords
{
    protected static string $resource = EnquiryResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
