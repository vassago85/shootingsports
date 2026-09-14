<?php

namespace App\Models\Concerns;

use App\Models\Discipline;
use Illuminate\Database\Eloquent\Relations\MorphToMany;

trait HasDisciplines
{
    public function disciplines(): MorphToMany
    {
        return $this->morphToMany(Discipline::class, 'disciplinable')
            ->withPivot('is_primary');
    }

    public function attachDiscipline(Discipline $discipline, bool $primary = false): void
    {
        $this->disciplines()->syncWithoutDetaching([
            $discipline->getKey() => ['is_primary' => $primary],
        ]);
    }

    public function detachDiscipline(Discipline $discipline): void
    {
        $this->disciplines()->detach($discipline->getKey());
    }

    /**
     * @param  list<int>  $ids
     */
    public function syncDisciplines(array $ids, ?int $primaryId = null): void
    {
        $payload = [];

        foreach ($ids as $id) {
            $payload[$id] = ['is_primary' => $primaryId !== null && (int) $id === $primaryId];
        }

        $this->disciplines()->sync($payload);
    }

    public function primaryDiscipline(): ?Discipline
    {
        $this->loadMissing('disciplines');

        return $this->disciplines->firstWhere('pivot.is_primary', true);
    }
}
