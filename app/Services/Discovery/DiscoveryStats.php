<?php

namespace App\Services\Discovery;

use App\Enums\Province;
use App\Models\Discipline;
use App\Models\Event;
use App\Models\Organisation;
use App\Models\Venue;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Query helpers that derive discovery relationships from actual event
 * history rather than the manually maintained `disciplinables` pivot
 * alone.
 *
 * Rationale: if PPRC hosts a Precision Rifle match but nobody
 * remembered to attach `precision-rifle` to the club record, the
 * discipline page must still list PPRC and count Gauteng as an active
 * province. Derived-first, pivot as a supplement.
 *
 * A single "published" event is the source of truth for all
 * derivations. `Event::scopePublished` excludes only drafts (drafts
 * live in the desk workspace). This is intentional: past matches
 * still tell us "this club runs PRS in Gauteng", which is exactly
 * what a discovery visitor wants to know when the calendar is thin.
 */
class DiscoveryStats
{
    /**
     * Clubs (and series/associations) associated with a discipline —
     * either via the explicit `disciplinables` pivot or by having
     * hosted published events in the discipline tree.
     *
     * @return EloquentCollection<int, Organisation>
     */
    public function clubsForDiscipline(Discipline $discipline, ?Province $province = null): EloquentCollection
    {
        $treeIds = $discipline->treeIds();

        return Organisation::query()
            ->published()
            ->clubs()
            ->where(function (Builder $q) use ($treeIds): void {
                $q->whereHas('disciplines', fn (Builder $d) => $d->whereIn('disciplines.id', $treeIds))
                    ->orWhereHas('hostedEvents', function (Builder $e) use ($treeIds): void {
                        $e->published()
                            ->whereHas('disciplines', fn (Builder $d) => $d->whereIn('disciplines.id', $treeIds));
                    });
            })
            ->when($province, fn (Builder $q) => $q->where('province', $province))
            ->with('disciplines')
            ->orderBy('name')
            ->get();
    }

    /**
     * Count of distinct provinces where a discipline is active —
     * unions provinces from (a) clubs that list the discipline,
     * (b) venues of published matches in the discipline, and
     * (c) hosts of published matches without a venue pin.
     */
    public function activeProvincesForDiscipline(Discipline $discipline): int
    {
        return $this->activeProvincesCollection($discipline)->count();
    }

    /**
     * @return Collection<int, Province>
     */
    public function activeProvincesCollectionForDiscipline(Discipline $discipline): Collection
    {
        return $this->activeProvincesCollection($discipline);
    }

    /**
     * Count of ranges (published venues) associated with a discipline —
     * either via the explicit pivot or by having hosted a published
     * match in the discipline tree.
     */
    public function rangesCountForDiscipline(Discipline $discipline, ?Province $province = null): int
    {
        $treeIds = $discipline->treeIds();

        return Venue::query()
            ->published()
            ->when($province, fn (Builder $q) => $q->where('province', $province))
            ->where(function (Builder $q) use ($treeIds): void {
                $q->whereHas('disciplines', fn (Builder $d) => $d->whereIn('disciplines.id', $treeIds))
                    ->orWhereHas('events', function (Builder $e) use ($treeIds): void {
                        $e->published()
                            ->whereHas('disciplines', fn (Builder $d) => $d->whereIn('disciplines.id', $treeIds));
                    });
            })
            ->count();
    }

    /**
     * Disciplines associated with an organisation — explicit pivot
     * merged with disciplines derived from its published hosted
     * events. Ordered by name for stable UI output.
     *
     * @return EloquentCollection<int, Discipline>
     */
    public function inferredDisciplinesForOrganisation(Organisation $organisation): EloquentCollection
    {
        $ids = $organisation->disciplines()->pluck('disciplines.id')
            ->merge(
                Discipline::query()
                    ->whereHas('events', function (Builder $q) use ($organisation): void {
                        $q->published()->where('host_organisation_id', $organisation->id);
                    })
                    ->pluck('id')
            )
            ->unique()
            ->values();

        if ($ids->isEmpty()) {
            return new EloquentCollection;
        }

        return Discipline::query()
            ->whereIn('id', $ids)
            ->orderBy('name')
            ->get();
    }

    /**
     * Ranges commonly used by an organisation, ordered by how many
     * published events they have hosted for it.
     *
     * @return EloquentCollection<int, Venue>
     */
    public function commonRangesForOrganisation(Organisation $organisation, int $limit = 6): EloquentCollection
    {
        // Union the legacy `events.venue_id` column with the
        // `event_venue` pivot so multi-venue matches contribute every
        // venue they used, not just the primary.
        $ids = collect()
            ->merge(
                Event::query()
                    ->where('host_organisation_id', $organisation->id)
                    ->published()
                    ->whereNotNull('venue_id')
                    ->select('venue_id')
                    ->selectRaw('count(*) as event_count')
                    ->groupBy('venue_id')
                    ->orderByDesc('event_count')
                    ->pluck('venue_id')
            )
            ->merge(
                DB::table('event_venue')
                    ->join('events', 'events.id', '=', 'event_venue.event_id')
                    ->whereNull('events.deleted_at')
                    ->where('events.host_organisation_id', $organisation->id)
                    ->where('events.status', '!=', 'draft')
                    ->select('event_venue.venue_id')
                    ->selectRaw('count(*) as event_count')
                    ->groupBy('event_venue.venue_id')
                    ->orderByDesc('event_count')
                    ->pluck('venue_id')
            )
            ->unique()
            ->take($limit)
            ->values()
            ->all();

        return $this->hydrateInOrder(Venue::query()->published(), $ids);
    }

    /**
     * Disciplines associated with a venue — explicit pivot merged with
     * disciplines derived from published events hosted at the venue.
     *
     * @return EloquentCollection<int, Discipline>
     */
    public function inferredDisciplinesForVenue(Venue $venue): EloquentCollection
    {
        $ids = $venue->disciplines()->pluck('disciplines.id')
            ->merge(
                Discipline::query()
                    ->whereHas('events', function (Builder $q) use ($venue): void {
                        $q->published()->where('venue_id', $venue->id);
                    })
                    ->pluck('id')
            )
            ->unique()
            ->values();

        if ($ids->isEmpty()) {
            return new EloquentCollection;
        }

        return Discipline::query()
            ->whereIn('id', $ids)
            ->orderBy('name')
            ->get();
    }

    /**
     * Clubs that have hosted published matches at this venue, ordered
     * by how many matches they have hosted here.
     *
     * @return EloquentCollection<int, Organisation>
     */
    public function clubsUsingVenue(Venue $venue, int $limit = 10): EloquentCollection
    {
        // Union the legacy single-venue column with the multi-venue
        // pivot: a Day-2 match at this range still counts its host as
        // a regular here.
        $ids = collect()
            ->merge(
                Event::query()
                    ->where('venue_id', $venue->id)
                    ->published()
                    ->whereNotNull('host_organisation_id')
                    ->select('host_organisation_id')
                    ->selectRaw('count(*) as event_count')
                    ->groupBy('host_organisation_id')
                    ->orderByDesc('event_count')
                    ->pluck('host_organisation_id')
            )
            ->merge(
                DB::table('event_venue')
                    ->join('events', 'events.id', '=', 'event_venue.event_id')
                    ->whereNull('events.deleted_at')
                    ->where('event_venue.venue_id', $venue->id)
                    ->where('events.status', '!=', 'draft')
                    ->whereNotNull('events.host_organisation_id')
                    ->select('events.host_organisation_id')
                    ->selectRaw('count(*) as event_count')
                    ->groupBy('events.host_organisation_id')
                    ->orderByDesc('event_count')
                    ->pluck('host_organisation_id')
            )
            ->unique()
            ->take($limit)
            ->values()
            ->all();

        return $this->hydrateInOrder(Organisation::query()->published(), $ids);
    }

    /**
     * @return Collection<int, Province>
     */
    private function activeProvincesCollection(Discipline $discipline): Collection
    {
        $treeIds = $discipline->treeIds();

        $fromClubs = Organisation::query()
            ->whereNotNull('province')
            ->whereHas('disciplines', fn (Builder $q) => $q->whereIn('disciplines.id', $treeIds))
            ->distinct()
            ->pluck('province');

        $fromEventVenues = Venue::query()
            ->whereNotNull('province')
            ->whereHas('events', function (Builder $e) use ($treeIds): void {
                $e->published()
                    ->whereHas('disciplines', fn (Builder $d) => $d->whereIn('disciplines.id', $treeIds));
            })
            ->distinct()
            ->pluck('province');

        $fromEventHosts = Organisation::query()
            ->whereNotNull('province')
            ->whereHas('hostedEvents', function (Builder $e) use ($treeIds): void {
                $e->published()
                    ->whereNull('venue_id')
                    ->whereHas('disciplines', fn (Builder $d) => $d->whereIn('disciplines.id', $treeIds));
            })
            ->distinct()
            ->pluck('province');

        // Casts return the enum; when reading via distinct()->pluck we
        // get the raw column string on some driver + query-log paths,
        // so normalise back to Province.
        return $fromClubs
            ->merge($fromEventVenues)
            ->merge($fromEventHosts)
            ->map(fn ($value): ?Province => $value instanceof Province ? $value : Province::tryFrom((string) $value))
            ->filter()
            ->unique(fn (Province $p): string => $p->value)
            ->values();
    }

    /**
     * Hydrate models in the exact id-order they came out of the
     * ranking query — Postgres does not preserve `whereIn` order.
     *
     * @template TModel of \Illuminate\Database\Eloquent\Model
     *
     * @param  Builder<TModel>  $baseQuery
     * @param  list<int>  $ids
     * @return EloquentCollection<int, TModel>
     */
    private function hydrateInOrder(Builder $baseQuery, array $ids): EloquentCollection
    {
        if ($ids === []) {
            /** @var EloquentCollection<int, TModel> $empty */
            $empty = new EloquentCollection;

            return $empty;
        }

        $models = $baseQuery->whereIn('id', $ids)->get()->keyBy('id');

        /** @var EloquentCollection<int, TModel> $ordered */
        $ordered = new EloquentCollection;

        foreach ($ids as $id) {
            $model = $models->get($id);

            if ($model !== null) {
                $ordered->push($model);
            }
        }

        return $ordered;
    }
}
