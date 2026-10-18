<?php

namespace App\Filament\Desk\Resources\MembershipInvoices\Pages;

use App\Filament\Desk\Resources\MembershipInvoices\MembershipInvoiceResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListMembershipInvoices extends ListRecords
{
    protected static string $resource = MembershipInvoiceResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
