<?php

namespace App\Filament\Desk\Resources\MagazineIssues\Pages;

use App\Filament\Desk\Resources\MagazineIssues\MagazineIssueResource;
use App\Jobs\ExtractMagazineText;
use App\Models\MagazineIssue;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditMagazineIssue extends EditRecord
{
    protected static string $resource = MagazineIssueResource::class;

    protected ?string $previousPdfPath = null;

    protected function beforeSave(): void
    {
        $record = $this->getRecord();

        if ($record instanceof MagazineIssue) {
            $this->previousPdfPath = $record->getOriginal('pdf_path');
        }
    }

    protected function afterSave(): void
    {
        $record = $this->getRecord();

        if (! $record instanceof MagazineIssue || $record->pdf_path === null) {
            return;
        }

        if ($record->pdf_path !== $this->previousPdfPath) {
            ExtractMagazineText::dispatch($record->id);
        }
    }

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
