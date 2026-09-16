<?php

namespace App\Filament\Resources\Venues\Pages;

use App\Enums\GeocodeSource;
use App\Filament\Resources\Venues\VenueResource;
use Filament\Resources\Pages\CreateRecord;

class CreateVenue extends CreateRecord
{
    protected static string $resource = VenueResource::class;

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $lat = $data['lat'] ?? null;
        $lng = $data['lng'] ?? null;

        if ($lat !== null && $lat !== '' && $lng !== null && $lng !== '') {
            $data['geocode_source'] = GeocodeSource::Staff->value;
            $data['geocoded_at'] = now();
        }

        return $data;
    }
}
