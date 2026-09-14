<?php

namespace App\Filament\Desk\Resources\Events\Pages;

use App\Enums\ListingSource;
use App\Filament\Desk\Resources\Events\EventResource;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Facades\Auth;

class CreateEvent extends CreateRecord
{
    protected static string $resource = EventResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['created_by'] ??= Auth::id();
        $data['source'] = ListingSource::Submission->value;
        $data['all_day'] ??= true;

        return $data;
    }
}
