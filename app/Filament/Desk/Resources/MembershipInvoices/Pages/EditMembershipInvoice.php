<?php

namespace App\Filament\Desk\Resources\MembershipInvoices\Pages;

use App\Filament\Desk\Resources\MembershipInvoices\MembershipInvoiceResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditMembershipInvoice extends EditRecord
{
    protected static string $resource = MembershipInvoiceResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
