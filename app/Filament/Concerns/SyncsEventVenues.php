<?php

namespace App\Filament\Concerns;

use App\Filament\Support\EventVenueRepeater;

/**
 * Filament page mixin that keeps the multi-venue pivot in sync with
 * the form. Mirrors the SyncsEventDisciplines trait — pop the
 * repeater state out of the payload before save (so it doesn't try
 * to write to a non-existent column), then persist it after save
 * once the record is known.
 */
trait SyncsEventVenues
{
    /** @var list<array<string, mixed>> */
    protected array $pendingAdditionalVenues = [];

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function extractAdditionalVenues(array $data): array
    {
        $this->pendingAdditionalVenues = array_values(array_filter(
            is_array($data['additional_venues'] ?? null) ? $data['additional_venues'] : [],
            fn ($row): bool => is_array($row) && filled($row['venue_id'] ?? null),
        ));

        unset($data['additional_venues']);

        return $data;
    }

    protected function persistVenues(): void
    {
        $primaryVenueId = $this->record?->venue_id !== null ? (int) $this->record->venue_id : null;

        EventVenueRepeater::sync($this->record, $primaryVenueId, $this->pendingAdditionalVenues);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function hydrateAdditionalVenues(array $data): array
    {
        if ($this->record !== null) {
            $data['additional_venues'] = EventVenueRepeater::fillFromEvent($this->record);
        }

        return $data;
    }
}
