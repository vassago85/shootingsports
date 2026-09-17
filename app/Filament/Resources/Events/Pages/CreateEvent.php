<?php

namespace App\Filament\Resources\Events\Pages;

use App\Filament\Concerns\SyncsEventDisciplines;
use App\Filament\Concerns\SyncsEventVenues;
use App\Filament\Resources\Events\EventResource;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Facades\Auth;

class CreateEvent extends CreateRecord
{
    use SyncsEventDisciplines, SyncsEventVenues;

    protected static string $resource = EventResource::class;

    /**
     * Stamp the current admin as the creator. The form intentionally has no
     * "Created by" picker — it's always the person clicking Create.
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data = $this->extractDisciplineIds($data);
        $data = $this->extractAdditionalVenues($data);
        $data['created_by'] ??= Auth::id();

        return $data;
    }

    protected function afterCreate(): void
    {
        $this->persistDisciplines();
        $this->persistVenues();
    }

    public function areFormActionsSticky(): bool
    {
        return true;
    }
}
