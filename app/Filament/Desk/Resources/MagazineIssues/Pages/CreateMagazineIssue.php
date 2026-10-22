<?php

namespace App\Filament\Desk\Resources\MagazineIssues\Pages;

use App\Filament\Desk\Resources\MagazineIssues\MagazineIssueResource;
use App\Jobs\ExtractMagazineCover;
use App\Jobs\ExtractMagazineText;
use App\Models\MagazineIssue;
use Filament\Resources\Pages\CreateRecord;

class CreateMagazineIssue extends CreateRecord
{
    protected static string $resource = MagazineIssueResource::class;

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['user_id'] = auth()->id();

        return $data;
    }

    protected function afterCreate(): void
    {
        $record = $this->getRecord();

        if (! $record instanceof MagazineIssue || $record->pdf_path === null) {
            return;
        }

        ExtractMagazineText::dispatch($record->id);

        if ($record->cover_path === null) {
            ExtractMagazineCover::dispatch($record->id);
        }
    }
}
