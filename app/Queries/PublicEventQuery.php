<?php

namespace App\Queries;

use App\Enums\DisciplineFamily;
use App\Enums\Province;
use App\Models\Discipline;
use App\Models\Event;
use App\Support\Geo;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class PublicEventQuery
{
    public function __construct(
        public ?string $family = null,
        public ?string $disciplineSlug = null,
        public ?string $provinceSlug = null,
        public ?int $radiusKm = null,
        public ?Carbon $from = null,
        public ?Carbon $to = null,
        public bool $novice = false,
        public bool $confirmedOnly = false,
        public ?int $organisationId = null,
        public ?int $venueId = null,
        public array $disciplineIds = [],
        public ?int $limit = null,
    ) {}

    public static function fromRequest(Request $request): self
    {
        $radius = $request->integer('radius') ?: null;

        return new self(
            family: $request->string('family')->toString() ?: null,
            disciplineSlug: $request->string('discipline')->toString() ?: null,
            provinceSlug: $request->string('province')->toString() ?: null,
            radiusKm: $radius,
            from: self::date($request->input('from')),
            to: self::date($request->input('to')),
            novice: $request->boolean('novice'),
            confirmedOnly: $request->boolean('confirmed'),
        );
    }

    public function builder(): Builder
    {
        $query = Event::query()
            ->upcoming()
            ->with(['hostOrganisation.parent', 'venue', 'disciplines', 'flags', 'banner'])
            ->orderBy('starts_at');

        if ($this->confirmedOnly) {
            $query->confirmedOnly();
        }

        if ($this->organisationId) {
            $query->where('host_organisation_id', $this->organisationId);
        }

        if ($this->venueId) {
            $query->where('venue_id', $this->venueId);
        }

        $disciplineIds = $this->resolveDisciplineIds();

        if ($disciplineIds !== []) {
            $query->whereHas('disciplines', fn (Builder $q) => $q->whereIn('disciplines.id', $disciplineIds));
        }

        if ($this->family && $this->family !== 'all') {
            $family = DisciplineFamily::tryFrom($this->family);

            if ($family) {
                $query->whereHas('disciplines', fn (Builder $q) => $q->where('family', $family));
            }
        }

        $provinces = $this->resolvedProvinces();

        if ($provinces !== []) {
            $query->where(function (Builder $q) use ($provinces): void {
                $q->whereHas('venue', fn (Builder $venue) => $venue->whereIn('province', $provinces))
                    ->orWhere(function (Builder $withoutVenue) use ($provinces): void {
                        $withoutVenue->whereNull('venue_id')
                            ->whereHas('hostOrganisation', fn (Builder $org) => $org->whereIn('province', $provinces));
                    });
            });
        }

        if ($this->from) {
            $query->where('starts_at', '>=', $this->from->startOfDay());
        }

        if ($this->to) {
            $query->where('starts_at', '<=', $this->to->endOfDay());
        }

        if ($this->novice) {
            $query->whereHas('flags', fn (Builder $q) => $q->where('slug', 'new-shooter-friendly'));
        }

        if ($this->limit) {
            $query->limit($this->limit);
        }

        return $query;
    }

    /**
     * @return Collection<int, Event>
     */
    public function get(): Collection
    {
        $events = $this->builder()->get();

        return $this->applyRadius($events);
    }

    /**
     * @return list<int>
     */
    private function resolveDisciplineIds(): array
    {
        if ($this->disciplineIds !== []) {
            return $this->disciplineIds;
        }

        if (! $this->disciplineSlug) {
            return [];
        }

        $discipline = Discipline::query()->where('slug', $this->disciplineSlug)->first();

        return $discipline?->treeIds() ?? [];
    }

    /**
     * @param  Collection<int, Event>  $events
     * @return Collection<int, Event>
     */
    private function applyRadius(Collection $events): Collection
    {
        if (! $this->radiusKm) {
            return $events;
        }

        $provinces = $this->resolvedProvinces();

        if (count($provinces) !== 1) {
            return $events;
        }

        [$lat, $lng] = Geo::provinceCentroid($provinces[0]);

        return $events->filter(function (Event $event) use ($lat, $lng): bool {
            $venue = $event->venue;

            if ($venue?->lat === null || $venue->lng === null) {
                return false;
            }

            return Geo::haversineKm($lat, $lng, (float) $venue->lat, (float) $venue->lng) <= $this->radiusKm;
        })->values();
    }

    /**
     * @return list<Province>
     */
    public function resolvedProvinces(): array
    {
        if (! filled($this->provinceSlug)) {
            return [];
        }

        return collect(preg_split('/\s*,\s*/', $this->provinceSlug) ?: [])
            ->filter()
            ->map(fn (string $slug): ?Province => Province::fromUrlSlug($slug) ?? Province::tryFrom($slug))
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    private static function date(mixed $value): ?Carbon
    {
        if (! is_string($value) || $value === '') {
            return null;
        }

        try {
            return Carbon::parse($value);
        } catch (\Throwable) {
            return null;
        }
    }
}
