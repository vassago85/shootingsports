<?php

namespace App\Filament\Resources\Enquiries\Pages;

use App\Filament\Resources\Enquiries\EnquiryResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditEnquiry extends EditRecord
{
    protected static string $resource = EnquiryResource::class;

    public function mount(int|string $record): void
    {
        parent::mount($record);
        $this->getRecord()->markRead();
    }

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
