<?php

namespace App\Filament\Resources\Events\Pages;

use App\Filament\Concerns\SyncsEventDisciplines;
use App\Filament\Concerns\SyncsEventVenues;
use App\Filament\Resources\Events\EventResource;
use Filament\Actions\ActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Resources\Pages\EditRecord;
use Filament\Support\Icons\Heroicon;

class EditEvent extends EditRecord
{
    use SyncsEventDisciplines, SyncsEventVenues;

    protected static string $resource = EventResource::class;

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeFill(array $data): array
    {
        $data = $this->hydrateDisciplineIds($data);

        return $this->hydrateAdditionalVenues($data);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeSave(array $data): array
    {
        $data = $this->extractDisciplineIds($data);

        return $this->extractAdditionalVenues($data);
    }

    protected function afterSave(): void
    {
        $this->persistDisciplines();
        $this->persistVenues();
    }

    public function areFormActionsSticky(): bool
    {
        return true;
    }

    protected function getHeaderActions(): array
    {
        return [
            ActionGroup::make([
                DeleteAction::make(),
                ForceDeleteAction::make(),
                RestoreAction::make(),
            ])
                ->icon(Heroicon::EllipsisVertical)
                ->iconButton()
                ->tooltip('Actions'),
        ];
    }
}
