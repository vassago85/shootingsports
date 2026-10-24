<?php

namespace App\Queries;

use App\Enums\DisciplineFamily;
use App\Enums\Division;
use App\Enums\EventKind;
use App\Enums\Province;
use App\Models\Discipline;
use App\Models\Event;
use App\Models\Organisation;
use App\Models\User;
use App\Models\Venue;
use App\Services\Geocoding\VenueGeocoder;
use App\Support\Geo;
use App\Support\ThisWeekend;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

class PublicEventQuery
{
    public function __construct(
        public ?string $family = null,
        public ?string $disciplineSlug = null,
        public ?string $provinceSlug = null,
        public ?int $radiusKm = null,
        public ?float $nearLat = null,
        public ?float $nearLng = null,
        public ?string $near = null,
        public ?Carbon $from = null,
        public ?Carbon $to = null,
        public bool $weekend = false,
        public bool $novice = false,
        public bool $confirmedOnly = false,
        public ?string $kind = null,
        public ?int $organisationId = null,
        public ?int $venueId = null,
        public array $disciplineIds = [],
        public ?int $limit = null,
        /** @var list<int>|null */
        public ?array $eventIds = null,
    ) {
        if ($this->weekend) {
            [$weekendFrom, $weekendTo] = ThisWeekend::range();
            $this->from = $weekendFrom;
            $this->to = $weekendTo;
        }
    }

    /** How many events were dropped for lacking venue pins (after get()). */
    public int $unpinnedSkipped = 0;

    /**
     * Filter keys the suggestion engine tries to drop, one at a
     * time, when the current query returns zero rows. Ordered so a
     * tie-breaker prefers the most "narrowing" filter — removing
     * the date range or novice flag typically opens the list up
     * more than, say, flipping confirmed-only off.
     */
    private const SUGGESTIBLE_FILTERS = [
        'novice',
        'weekend',
        'from',
        'to',
        'province',
        'discipline',
        'family',
        'confirmed',
        'kind',
    ];

    public static function fromRequest(Request $request): self
    {
        $radius = $request->integer('radius') ?: null;
        $nearLat = $request->filled('lat') ? (float) $request->input('lat') : null;
        $nearLng = $request->filled('lng') ? (float) $request->input('lng') : null;
        $near = $request->string('near')->toString() ?: null;

        return new self(
            family: $request->string('family')->toString() ?: null,
            disciplineSlug: $request->string('discipline')->toString() ?: null,
            provinceSlug: $request->string('province')->toString() ?: null,
            radiusKm: $radius,
            nearLat: $nearLat,
            nearLng: $nearLng,
            near: $near,
            from: self::date($request->input('from')),
            to: self::date($request->input('to')),
            weekend: $request->boolean('weekend'),
            novice: $request->boolean('novice'),
            confirmedOnly: $request->boolean('confirmed'),
            kind: EventKind::tryFrom($request->string('kind')->toString())?->value,
            organisationId: self::publishedOrganisationId($request),
            venueId: self::publishedVenueId($request),
            eventIds: self::shooterEventIds($request),
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
            // Dual-read: the venue may be the legacy single `venue_id`
            // or one of several rows on the `event_venue` pivot. Both
            // paths must catch it so a Day 2 range still shows the
            // match on its /ranges/ page.
            $venueId = $this->venueId;
            $query->where(function (Builder $q) use ($venueId): void {
                $q->where('venue_id', $venueId)
                    ->orWhereHas('venues', fn (Builder $v) => $v->where('venues.id', $venueId));
            });
        }

        if ($this->eventIds !== null) {
            if ($this->eventIds === []) {
                $query->whereRaw('1 = 0');
            } else {
                $query->whereIn('id', $this->eventIds);
            }
        }

        $disciplineIds = $this->resolveDisciplineIds();

        if ($disciplineIds !== []) {
            $query->whereHas('disciplines', fn (Builder $q) => $q->whereIn('disciplines.id', $disciplineIds));
        }

        if ($this->family && $this->family !== 'all') {
            $division = Division::tryFrom($this->family);

            if ($division) {
                $query->whereHas('disciplines.divisionLinks', fn (Builder $q) => $q->where('division', $division));
            } else {
                $family = DisciplineFamily::tryFrom($this->family);

                if ($family) {
                    $query->whereHas('disciplines', fn (Builder $q) => $q->where('family', $family));
                }
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
            $from = $this->from->copy()->startOfDay();
            $query->where(function (Builder $window) use ($from): void {
                $window->where('starts_at', '>=', $from)
                    ->orWhere(function (Builder $stillRunning) use ($from): void {
                        $stillRunning->whereNotNull('ends_at')
                            ->where('ends_at', '>=', $from);
                    });
            });
        }

        if ($this->to) {
            $query->where('starts_at', '<=', $this->to->endOfDay());
        }

        if ($this->novice) {
            $query->whereHas('flags', fn (Builder $q) => $q->where('slug', 'new-shooter-friendly'));
        }

        $kind = EventKind::tryFrom((string) $this->kind);

        if ($kind instanceof EventKind) {
            $query->where('kind', $kind);
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
     * Suggest a single filter whose removal would surface the most
     * matches, for use in the mobile calendar's empty state ("Remove
     * Beginner-friendly to see 4 matches"). Returns null when no
     * single-filter removal helps — the calendar then falls back to
     * the plain "no matches" copy with a Clear filters button.
     *
     * The server returns the raw filter key and the resulting count
     * only. The client owns the display copy so wording stays close
     * to the chip label the user is looking at.
     *
     * @return array{filter: string, would_show: int}|null
     */
    public function suggestFilterToRemove(): ?array
    {
        if (! $this->hasRemovableFilters()) {
            return null;
        }

        $best = null;

        foreach (self::SUGGESTIBLE_FILTERS as $filter) {
            if (! $this->filterIsActive($filter)) {
                continue;
            }

            $count = $this->countWithFilterRemoved($filter);

            if ($count <= 0) {
                continue;
            }

            if ($best === null || $count > $best['would_show']) {
                $best = ['filter' => $filter, 'would_show' => $count];
            }
        }

        return $best;
    }

    private function hasRemovableFilters(): bool
    {
        foreach (self::SUGGESTIBLE_FILTERS as $filter) {
            if ($this->filterIsActive($filter)) {
                return true;
            }
        }

        return false;
    }

    private function filterIsActive(string $filter): bool
    {
        return match ($filter) {
            'novice' => $this->novice,
            'weekend' => $this->weekend,
            'from' => $this->from !== null && ! $this->weekend,
            'to' => $this->to !== null && ! $this->weekend,
            'province' => filled($this->provinceSlug),
            'discipline' => filled($this->disciplineSlug) || $this->disciplineIds !== [],
            'family' => filled($this->family) && $this->family !== 'all',
            'confirmed' => $this->confirmedOnly,
            'kind' => filled($this->kind),
            default => false,
        };
    }

    private function countWithFilterRemoved(string $filter): int
    {
        $clone = clone $this;
        $clone->unpinnedSkipped = 0;

        match ($filter) {
            'novice' => $clone->novice = false,
            'weekend' => $this->clearWeekend($clone),
            'from' => $clone->from = null,
            'to' => $clone->to = null,
            'province' => $clone->provinceSlug = null,
            'discipline' => $clone->clearDiscipline(),
            'family' => $clone->family = null,
            'confirmed' => $clone->confirmedOnly = false,
            'kind' => $clone->kind = null,
            default => null,
        };

        return $clone->get()->count();
    }

    private function clearWeekend(self $clone): void
    {
        $clone->weekend = false;
        $clone->from = null;
        $clone->to = null;
    }

    private function clearDiscipline(): void
    {
        $this->disciplineSlug = null;
        $this->disciplineIds = [];
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
        $this->unpinnedSkipped = 0;

        if (! $this->radiusKm || $this->radiusKm <= 0) {
            return $events;
        }

        $origin = $this->resolveOrigin();

        if ($origin === null) {
            // Radius without a usable origin is a no-op — better than
            // pretending province centroids are "near me".
            return $events;
        }

        [$lat, $lng] = $origin;
        $skipped = 0;

        $filtered = $events->filter(function (Event $event) use ($lat, $lng, &$skipped): bool {
            $venue = $event->venue;

            if ($venue === null || ! $venue->hasCoordinates()) {
                $skipped++;

                return false;
            }

            return Geo::haversineKm($lat, $lng, (float) $venue->lat, (float) $venue->lng) <= $this->radiusKm;
        })->values();

        $this->unpinnedSkipped = $skipped;

        return $filtered;
    }

    /**
     * @return array{0: float, 1: float}|null
     */
    private function resolveOrigin(): ?array
    {
        if ($this->nearLat !== null && $this->nearLng !== null
            && Geo::isInsideSouthAfrica($this->nearLat, $this->nearLng)) {
            return [$this->nearLat, $this->nearLng];
        }

        if (! filled($this->near)) {
            return null;
        }

        $cacheKey = 'geocode.near.'.md5(mb_strtolower(trim($this->near)));

        $result = Cache::remember($cacheKey, 86400, function () {
            return app(VenueGeocoder::class)->geocodePlace((string) $this->near);
        });

        if ($result === null) {
            return null;
        }

        return [$result->lat, $result->lng];
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

    private static function publishedOrganisationId(Request $request): ?int
    {
        $slug = $request->string('organisation')->toString()
            ?: $request->string('club')->toString();

        if ($slug === '') {
            return null;
        }

        return Organisation::query()->published()->where('slug', $slug)->value('id') ?? 0;
    }

    /**
     * @return list<int>|null
     */
    private static function shooterEventIds(Request $request): ?array
    {
        $slug = $request->string('shooter')->toString();

        if ($slug === '') {
            return null;
        }

        $user = User::query()->where('calendar_slug', $slug)->first();

        if (! $user) {
            return [];
        }

        return $user->savedEvents()->pluck('events.id')->all();
    }

    private static function publishedVenueId(Request $request): ?int
    {
        $slug = $request->string('venue')->toString();

        if ($slug === '') {
            return null;
        }

        return Venue::query()->published()->where('slug', $slug)->value('id') ?? 0;
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
