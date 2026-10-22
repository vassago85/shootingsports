<?php

namespace App\Filament\Desk\Resources\MagazineIssues\Pages;

use App\Filament\Desk\Resources\MagazineIssues\MagazineIssueResource;
use App\Jobs\ExtractMagazineCover;
use App\Jobs\ExtractMagazineText;
use App\Models\MagazineIssue;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditMagazineIssue extends EditRecord
{
    protected static string $resource = MagazineIssueResource::class;

    protected ?string $previousPdfPath = null;

    protected ?string $previousCoverPath = null;

    protected function beforeSave(): void
    {
        $record = $this->getRecord();

        if ($record instanceof MagazineIssue) {
            $this->previousPdfPath = $record->getOriginal('pdf_path');
            $this->previousCoverPath = $record->getOriginal('cover_path');
        }
    }

    protected function afterSave(): void
    {
        $record = $this->getRecord();

        if (! $record instanceof MagazineIssue || $record->pdf_path === null) {
            return;
        }

        $pdfChanged = $record->pdf_path !== $this->previousPdfPath;

        if ($pdfChanged || ! $record->hasReadableText()) {
            ExtractMagazineText::dispatch($record->id);
        }

        // Auto-extract a cover when (a) the PDF was swapped and the
        // editor left the cover blank, or (b) the editor cleared an
        // existing cover and wants a fresh one from page 1.
        $needsCover = $record->cover_path === null
            && ($pdfChanged || $this->previousCoverPath !== null);

        if ($needsCover) {
            ExtractMagazineCover::dispatch($record->id);
        }
    }

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
