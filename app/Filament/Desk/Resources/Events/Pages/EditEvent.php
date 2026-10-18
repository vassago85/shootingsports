<?php

namespace App\Filament\Desk\Resources\Events\Pages;

use App\Filament\Concerns\SyncsEventDisciplines;
use App\Filament\Concerns\SyncsEventFlags;
use App\Filament\Concerns\SyncsEventVenues;
use App\Filament\Desk\Resources\Events\EventResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditEvent extends EditRecord
{
    use SyncsEventDisciplines;
    use SyncsEventFlags;
    use SyncsEventVenues;

    protected static string $resource = EventResource::class;

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeFill(array $data): array
    {
        $data = $this->hydrateDisciplineIds($data);
        $data = $this->hydrateFlagIds($data);

        return $this->hydrateAdditionalVenues($data);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeSave(array $data): array
    {
        $data = $this->extractDisciplineIds($data);
        $data = $this->extractFlagIds($data);

        return $this->extractAdditionalVenues($data);
    }

    protected function afterSave(): void
    {
        $this->persistDisciplines();
        $this->persistVenues();
        $this->persistFlags();
    }

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
