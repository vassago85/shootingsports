<?php

namespace App\Filament\Resources\Events\Pages;

use App\Filament\Concerns\SyncsEventDisciplines;
use App\Filament\Concerns\SyncsEventFlags;
use App\Filament\Resources\Events\EventResource;
use Filament\Actions\ActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Resources\Pages\EditRecord;
use Filament\Support\Icons\Heroicon;

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
