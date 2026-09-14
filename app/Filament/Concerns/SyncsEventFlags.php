<?php

namespace App\Filament\Concerns;

use App\Filament\Support\EventFlagSelect;

trait SyncsEventFlags
{
    /** @var list<int> */
    protected array $pendingFlagIds = [];

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function extractFlagIds(array $data): array
    {
        $this->pendingFlagIds = array_values(array_filter(array_map(
            intval(...),
            $data['flag_ids'] ?? [],
        )));

        unset($data['flag_ids']);

        return $data;
    }

    protected function persistFlags(): void
    {
        EventFlagSelect::sync($this->record, $this->pendingFlagIds);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function hydrateFlagIds(array $data): array
    {
        $data['flag_ids'] = EventFlagSelect::idsFor($this->record);

        return $data;
    }
}
