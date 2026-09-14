<?php

namespace App\Filament\Concerns;

use App\Filament\Support\EventDisciplineSelect;

trait SyncsEventDisciplines
{
    /** @var list<int> */
    protected array $pendingDisciplineIds = [];

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function extractDisciplineIds(array $data): array
    {
        $this->pendingDisciplineIds = array_values(array_filter(array_map(
            intval(...),
            $data['discipline_ids'] ?? [],
        )));

        unset($data['discipline_ids']);

        return $data;
    }

    protected function persistDisciplines(): void
    {
        EventDisciplineSelect::sync($this->record, $this->pendingDisciplineIds);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function hydrateDisciplineIds(array $data): array
    {
        $data['discipline_ids'] = EventDisciplineSelect::idsFor($this->record);

        return $data;
    }
}
