<?php

namespace App\Filament\Resources\Disciplines\Pages;

use App\Filament\Concerns\SyncsDisciplineExtras;
use App\Filament\Resources\Disciplines\DisciplineResource;
use Filament\Resources\Pages\CreateRecord;

class CreateDiscipline extends CreateRecord
{
    use SyncsDisciplineExtras;

    protected static string $resource = DisciplineResource::class;

    protected function afterCreate(): void
    {
        $this->persistDivisionExtras();
    }
}
