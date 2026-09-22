<?php

namespace App\Models;

use App\Enums\DisciplineFamily;
use App\Enums\Division;
use App\Support\DivisionCatalog;
use Database\Factories\DisciplineFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Illuminate\Support\Collection;

#[Fillable([
    'slug', 'name', 'family', 'parent_id', 'federation_organisation_id',
    'short_blurb', 'body', 'typical_distances', 'equipment_rules',
    'sort_order', 'is_published',
])]
class Discipline extends Model
{
    /** @use HasFactory<DisciplineFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'family' => DisciplineFamily::class,
            'sort_order' => 'integer',
            'is_published' => 'boolean',
        ];
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id')->orderBy('sort_order');
    }

    public function federation(): BelongsTo
    {
        return $this->belongsTo(Organisation::class, 'federation_organisation_id');
    }

    public function divisionLinks(): HasMany
    {
        return $this->hasMany(DisciplineDivision::class);
    }

    public function videos(): HasMany
    {
        return $this->hasMany(DisciplineVideo::class)->orderBy('sort_order');
    }

    /**
     * @return Collection<int, Division>
     */
    public function divisions(): Collection
    {
        $this->loadMissing('divisionLinks');

        return $this->divisionLinks
            ->map(fn (DisciplineDivision $link): ?Division => $link->division)
            ->filter()
            ->values();
    }

    /**
     * @param  list<Division>  $divisions
     */
    public function syncDivisions(array $divisions): void
    {
        $values = array_map(fn (Division $division): string => $division->value, $divisions);

        $this->divisionLinks()->whereNotIn('division', $values === [] ? [''] : $values)->delete();

        foreach ($values as $value) {
            $this->divisionLinks()->firstOrCreate(['division' => $value]);
        }

        if ($divisions !== []) {
            $this->forceFill([
                'family' => DivisionCatalog::familyFor($divisions),
            ])->saveQuietly();
        }
    }

    public function organisations(): MorphToMany
    {
        return $this->morphedByMany(Organisation::class, 'disciplinable')->withPivot('is_primary');
    }

    public function venues(): MorphToMany
    {
        return $this->morphedByMany(Venue::class, 'disciplinable')->withPivot('is_primary');
    }

    public function events(): MorphToMany
    {
        return $this->morphedByMany(Event::class, 'disciplinable')->withPivot('is_primary');
    }

    public function providers(): MorphToMany
    {
        return $this->morphedByMany(Provider::class, 'disciplinable')->withPivot('is_primary');
    }

    /**
     * @return list<int>
     */
    public function treeIds(): array
    {
        $ids = [$this->id];

        if ($this->parent_id === null) {
            $ids = array_merge($ids, $this->children()->pluck('id')->all());
        }

        return $ids;
    }

    /**
     * Upcoming event counts keyed by discipline id, including parent totals.
     *
     * @return array<int, int>
     */
    public static function upcomingCounts(): array
    {
        $counts = [];

        foreach (Event::query()->upcoming()->with('disciplines')->get() as $event) {
            // Build the set of discipline ids this event should
            // contribute to *before* incrementing, so an event tagged
            // with both a parent and a child (or two siblings) never
            // adds more than +1 to the parent tile.
            $countedIds = [];

            foreach ($event->disciplines as $discipline) {
                $countedIds[$discipline->id] = true;
                $rootId = $discipline->parent_id ?? $discipline->id;

                if ($rootId !== $discipline->id) {
                    $countedIds[$rootId] = true;
                }
            }

            foreach (array_keys($countedIds) as $id) {
                $counts[$id] = ($counts[$id] ?? 0) + 1;
            }
        }

        return $counts;
    }

    public function siblings()
    {
        $parentId = $this->parent_id ?? $this->id;

        return static::query()
            ->where(function ($query) use ($parentId): void {
                $query->where('parent_id', $parentId)
                    ->orWhere('id', $parentId);
            })
            ->where('id', '!=', $this->id)
            ->where('is_published', true)
            ->orderBy('sort_order');
    }
}
