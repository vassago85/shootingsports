<?php

namespace App\Filament\Resources\Disciplines\Pages;

use App\Filament\Concerns\SyncsDisciplineExtras;
use App\Filament\Resources\Disciplines\DisciplineResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditDiscipline extends EditRecord
{
    use SyncsDisciplineExtras;

    protected static string $resource = DisciplineResource::class;

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeFill(array $data): array
    {
        return $this->fillDivisionExtras($data);
    }

    protected function afterSave(): void
    {
        $this->persistDivisionExtras();
    }

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
