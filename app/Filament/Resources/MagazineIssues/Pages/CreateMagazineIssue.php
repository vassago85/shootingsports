<?php

namespace App\Filament\Resources\MagazineIssues\Pages;

use App\Filament\Resources\MagazineIssues\MagazineIssueResource;
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

        return $data;
    }

    protected function afterCreate(): void
    {
        if ($this->record instanceof MagazineIssue && blank($this->record->slug)) {
            $this->record->update(['slug' => 'issue-'.$this->record->id]);
        }
    }
}
