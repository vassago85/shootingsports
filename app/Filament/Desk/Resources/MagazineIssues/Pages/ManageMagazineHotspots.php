<?php

namespace App\Filament\Desk\Resources\MagazineIssues\Pages;

use App\Filament\Desk\Resources\MagazineIssues\MagazineIssueResource;
use Filament\Actions\Action;
use Filament\Resources\Pages\Concerns\InteractsWithRecord;
use Filament\Resources\Pages\Page;

class ManageMagazineHotspots extends Page
{
    use InteractsWithRecord;

    protected static string $resource = MagazineIssueResource::class;

    protected static ?string $title = 'Advertiser links';

    protected string $view = 'filament.resources.magazine-issues.hotspots';

    public function mount(int|string $record): void
    {
        $this->record = $this->resolveRecord($record);
        $this->authorizeAccess();
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('editIssue')
                ->label('Back to issue')
                ->url(fn (): string => $this->getResourceUrl('edit')),
        ];
    }
}
