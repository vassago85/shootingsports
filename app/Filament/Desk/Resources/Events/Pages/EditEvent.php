<?php

namespace App\Filament\Desk\Resources\Events\Pages;

use App\Filament\Concerns\SyncsEventDisciplines;
use App\Filament\Concerns\SyncsEventFlags;
use App\Filament\Desk\Resources\Events\EventResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditEvent extends EditRecord
{
    use SyncsEventDisciplines;
    use SyncsEventFlags;

    protected static string $resource = EventResource::class;

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeFill(array $data): array
    {
        return $this->hydrateFlagIds($this->hydrateDisciplineIds($data));
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeSave(array $data): array
    {
        return $this->extractFlagIds($this->extractDisciplineIds($data));
    }

    protected function afterSave(): void
    {
        $this->persistDisciplines();
        $this->persistFlags();
    }

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
