<?php

namespace App\Filament\Desk\Resources\Events\Pages;

use App\Enums\ListingSource;
use App\Filament\Concerns\SyncsEventDisciplines;
use App\Filament\Concerns\SyncsEventFlags;
use App\Filament\Desk\Resources\Events\EventResource;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Facades\Auth;

class CreateEvent extends CreateRecord
{
    use SyncsEventDisciplines;
    use SyncsEventFlags;

    protected static string $resource = EventResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data = $this->extractDisciplineIds($data);
        $data = $this->extractFlagIds($data);
        $data['created_by'] ??= Auth::id();
        $data['source'] = ListingSource::Submission->value;
        $data['all_day'] ??= true;

        return $data;
    }

    protected function afterCreate(): void
    {
        $this->persistDisciplines();
        $this->persistFlags();
    }
}
