<?php

namespace App\Filament\Resources\MagazineIssues\Pages;

use App\Filament\Resources\MagazineIssues\MagazineIssueResource;
use App\Jobs\ExtractMagazineLinks;
use App\Jobs\RenderMagazinePages;
use App\Models\MagazineIssue;
use Filament\Actions\Action;
use Filament\Resources\Pages\EditRecord;

class EditMagazineIssue extends EditRecord
{
    protected static string $resource = MagazineIssueResource::class;

    protected function afterSave(): void
    {
        $record = $this->getRecord();

        if (! $record instanceof MagazineIssue || $record->pdf_path === null) {
            return;
        }

        ExtractMagazineLinks::dispatch($record->id);
        RenderMagazinePages::dispatch($record->id);
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('hotspots')
                ->label('Advertiser links')
                ->url(fn (): string => MagazineIssueResource::getUrl('hotspots', ['record' => $this->getRecord()])),
        ];
    }
}
