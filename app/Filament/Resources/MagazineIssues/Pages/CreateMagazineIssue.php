<?php

namespace App\Filament\Resources\MagazineIssues\Pages;

use App\Filament\Resources\MagazineIssues\MagazineIssueResource;
use App\Jobs\ExtractMagazineCover;
use App\Jobs\ExtractMagazineLinks;
use App\Jobs\ExtractMagazineText;
use App\Jobs\RenderMagazinePages;
use App\Models\MagazineIssue;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Str;

class CreateMagazineIssue extends CreateRecord
{
    protected static string $resource = MagazineIssueResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['publisher_name'] = $data['publication'] ?? 'On Target Africa';
        $data['slug'] = Str::slug(($data['publication'] ?? 'issue').'-'.($data['issue_number'] ?? $data['title']));
        $data['issue_label'] = isset($data['issue_number']) ? 'Issue '.$data['issue_number'] : null;
        $data['language'] = 'en';
        $data['reader_access'] = 'public';
        $data['published_at'] = $data['issue_month'] ?? now();
        $data['user_id'] = auth()->id();
        $data['pdf_download_enabled'] = filled($data['pdf_path'] ?? null);
        $data['external_url'] = null;

        return $data;
    }

    protected function afterCreate(): void
    {
        if (! $this->record instanceof MagazineIssue || $this->record->pdf_path === null) {
            return;
        }

        ExtractMagazineText::dispatch($this->record->id);
        ExtractMagazineLinks::dispatch($this->record->id);
        RenderMagazinePages::dispatch($this->record->id);

        if ($this->record->cover_path === null) {
            ExtractMagazineCover::dispatch($this->record->id);
        }
    }
}
