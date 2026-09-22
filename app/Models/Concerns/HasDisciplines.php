<?php

namespace App\Models\Concerns;

use App\Enums\Division;
use App\Models\Discipline;
use Illuminate\Database\Eloquent\Builder;
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

    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeInDivision(Builder $query, Division $division): Builder
    {
        return $query->whereHas(
            'disciplines.divisionLinks',
            fn (Builder $links) => $links->where('division', $division),
        );
    }

    public function primaryDiscipline(): ?Discipline
    {
        $this->loadMissing('disciplines');

        return $this->disciplines->firstWhere('pivot.is_primary', true);
    }
}
