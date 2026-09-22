<?php

namespace App\Support\Mockups;

use App\Enums\AdPage;
use App\Enums\Division;
use App\Enums\EventStatus;
use App\Enums\OrganisationType;
use App\Enums\PlacementSlot;
use App\Enums\VerificationState;
use App\Models\Discipline;
use App\Models\Event;
use App\Models\Follow;
use App\Models\Organisation;
use App\Models\Placement;
use App\Models\Provider;
use App\Models\User;
use App\Models\Venue;
use App\Support\EventDate;
use App\Support\Money;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Read-only shapes for the redesign mockups.
 *
 * Fixture rows from local test runs (slug prefix "verify-" or "Verify "
 * names, and the known faker provider blurb) are left out so the mockups
 * show the register, not Pest leftovers. Fields the schema does not store
 * are returned as null and flagged in `schema_gaps` — views must not invent them.
 */
class MockupCatalog
{
    /** @var array<string, mixed> */
    private array $memo = [];

    /**
     * @return array{matches: int, ranges: int, sports: int, clubs: int}
     */
    public function stats(): array
    {
        return $this->memo('stats', fn (): array => [
            'matches' => $this->publicMatches()->count(),
            'ranges' => $this->ranges()->count(),
            'sports' => $this->sports()->count(),
            'clubs' => $this->clubs()->count(),
        ]);
    }

    /**
     * @return array{match: ?string, sport: ?string, club: ?string, range: ?string, business: ?string}
     */
    public function samples(): array
    {
        return $this->memo('samples', function (): array {
            $match = $this->publicMatches()->first(fn (array $row): bool => strlen((string) $row['description']) > 80)
                ?? $this->publicMatches()->first();
            $sport = $this->sports()->firstWhere('slug', 'precision-rifle') ?? $this->sports()->first();
            $club = $this->clubs()->sortByDesc('upcoming_count')->first();
            $range = $this->ranges()->sortByDesc(fn (array $row): int => ($row['max_distance_m'] !== null ? 2 : 0) + ($row['has_gps'] ? 1 : 0))->first();

            return [
                'match' => $match['slug'] ?? null,
                'sport' => $sport['slug'] ?? null,
                'club' => $club['slug'] ?? null,
                'range' => $range['slug'] ?? null,
                'business' => $this->directoryBusinesses()->first()['slug'] ?? null,
            ];
        });
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    public function publicMatches(): Collection
    {
        return $this->memo('publicMatches', function (): Collection {
            return Event::query()
                ->with(['disciplines', 'hostOrganisation.parent', 'venue', 'flags'])
                ->published()
                ->upcoming()
                ->where('slug', 'not like', 'verify-%')
                ->orderBy('starts_at')
                ->get()
                ->reject(fn (Event $event): bool => $this->isFixture($event->slug, $event->title))
                ->map(fn (Event $event): array => $this->shapeEvent($event))
                ->values();
        });
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $matches
     * @param  array<string, mixed>  $filters
     * @return array{matches: Collection<int, array<string, mixed>>, unlocated: int}
     */
    public function filterMatches(Collection $matches, array $filters): array
    {
        $sport = $this->blank($filters['sport'] ?? null);
        $sportSlugs = $this->sportFilterSlugs($sport);
        $province = $this->blank($filters['province'] ?? null);
        $level = $this->blank($filters['level'] ?? null);
        $from = $this->blank($filters['from'] ?? null);
        $to = $this->blank($filters['to'] ?? null);
        $fee = $this->blank($filters['fee'] ?? null);
        $sort = $this->blank($filters['sort'] ?? null) ?? 'soonest';
        $confirmed = ($filters['confirmed'] ?? null) === '1';
        $beginner = ($filters['beginner'] ?? null) === '1';
        $division = Division::tryFrom((string) ($filters['division'] ?? ''));
        $divisionSlugs = $division?->isPublic() === true ? $this->sportSlugsForDivision($division) : null;
        $lat = is_numeric($filters['lat'] ?? null) ? (float) $filters['lat'] : null;
        $lng = is_numeric($filters['lng'] ?? null) ? (float) $filters['lng'] : null;
        $radius = is_numeric($filters['radius'] ?? null) ? (float) $filters['radius'] : null;

        $filtered = $matches->filter(function (array $match) use ($sportSlugs, $province, $level, $from, $to, $fee, $confirmed, $beginner, $divisionSlugs): bool {
            if ($sportSlugs !== null && array_intersect($sportSlugs, $match['discipline_slugs']) === []) {
                return false;
            }

            if ($divisionSlugs !== null && array_intersect($divisionSlugs, $match['discipline_slugs']) === []) {
                return false;
            }

            if ($province !== null && $province !== 'near' && $match['province_value'] !== $province) {
                return false;
            }

            if ($level !== null && $match['level_value'] !== $level) {
                return false;
            }

            if ($from !== null && $match['date'] < $from) {
                return false;
            }

            if ($to !== null && $match['date'] > $to) {
                return false;
            }

            if ($fee === 'listed' && $match['fee_cents'] === null) {
                return false;
            }

            if ($confirmed && ! in_array($match['status_value'], ['confirmed', 'entries_open', 'full'], true)) {
                return false;
            }

            if ($beginner && ! in_array('new-shooter-friendly', $match['flag_slugs'], true)) {
                return false;
            }

            return true;
        })->values();

        $unlocated = 0;

        if ($lat !== null && $lng !== null) {
            $filtered = $filtered->map(function (array $match) use ($lat, $lng): array {
                $match['distance_km'] = $this->kilometres(
                    $match['lat'],
                    $match['lng'],
                    $lat,
                    $lng,
                );

                return $match;
            });

            if ($radius !== null) {
                $unlocated = $filtered->filter(fn (array $match): bool => $match['distance_km'] === null)->count();
                $filtered = $filtered->filter(fn (array $match): bool => $match['distance_km'] !== null && $match['distance_km'] <= $radius)->values();
            }

            if ($sort === 'closest') {
                $filtered = $filtered->sortBy(fn (array $match): float => $match['distance_km'] ?? INF)->values();
            }
        }

        if ($sort === 'recent') {
            $filtered = $filtered->sortByDesc('created_at')->values();
        }

        if ($sort === 'soonest' || ($sort === 'closest' && $lat === null)) {
            $filtered = $filtered->sortBy('starts_at')->values();
        }

        return ['matches' => $filtered, 'unlocated' => $unlocated];
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    public function sports(): Collection
    {
        return $this->memo('sports', function (): Collection {
            return Discipline::query()
                ->where('is_published', true)
                ->where('slug', 'not like', 'verify-%')
                ->with([
                    'federation',
                    'parent',
                    'children' => function (HasMany $query): void {
                        $query->where('is_published', true)
                            ->orderBy('sort_order')
                            ->withCount([
                                'events as upcoming_count' => fn (Builder $events) => $events->upcoming(),
                            ]);
                    },
                ])
                ->withCount([
                    'organisations as clubs_count' => fn (Builder $query) => $query->whereIn('type', [
                        OrganisationType::Club->value,
                        OrganisationType::Association->value,
                        OrganisationType::Series->value,
                    ]),
                    'venues as ranges_count',
                    'events as upcoming_count' => fn (Builder $query) => $query->upcoming(),
                ])
                ->orderBy('sort_order')
                ->orderBy('name')
                ->get()
                ->reject(fn (Discipline $sport): bool => $this->isFixture($sport->slug, $sport->name))
                ->map(fn (Discipline $sport): array => $this->shapeSport($sport))
                ->values();
        });
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    public function sportTiles(): Collection
    {
        $preferred = [
            'precision-rifle', 'pr22-rimfire', 'ipsc-practical', 'ipsc-rifle',
            'sporting-clays', 'trap', 'elr', 'hunting-rifle', 'nrl-hunter', 'gong-shooting',
        ];

        $sports = $this->sports();
        $picked = collect($preferred)
            ->map(fn (string $slug): ?array => $sports->firstWhere('slug', $slug))
            ->filter()
            ->values();

        if ($picked->count() >= 6) {
            return $picked->take(8);
        }

        return $sports->sortByDesc('upcoming_count')->take(8)->values();
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    public function clubs(): Collection
    {
        return $this->memo('clubs', function (): Collection {
            return Organisation::query()
                ->clubs()
                ->published()
                ->where('slug', 'not like', 'verify-%')
                ->with([
                    'disciplines',
                    'parent',
                    'hostedEvents' => fn ($query) => $query->upcoming()->with('venue')->orderBy('starts_at'),
                ])
                ->orderBy('name')
                ->get()
                ->reject(fn (Organisation $club): bool => $this->isFixture($club->slug, $club->name, $club->description))
                ->map(fn (Organisation $club): array => $this->shapeClub($club))
                ->values();
        });
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    public function ranges(): Collection
    {
        return $this->memo('ranges', function (): Collection {
            return Venue::query()
                ->published()
                ->where('slug', 'not like', 'verify-%')
                ->with([
                    'disciplines',
                    'events' => fn ($query) => $query->upcoming()->with('hostOrganisation')->orderBy('starts_at'),
                ])
                ->orderBy('name')
                ->get()
                ->reject(fn (Venue $range): bool => $this->isFixture($range->slug, $range->name))
                ->map(fn (Venue $range): array => $this->shapeRange($range))
                ->values();
        });
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    public function businesses(): Collection
    {
        return $this->memo('businesses', function (): Collection {
            return Provider::query()
                ->published()
                ->where('slug', 'not like', 'verify-%')
                ->with('disciplines')
                ->orderBy('name')
                ->get()
                ->reject(fn (Provider $business): bool => $this->isFixture($business->slug, $business->name, $business->description))
                ->map(fn (Provider $business): array => $this->shapeBusiness($business))
                ->values();
        });
    }

    /**
     * Published businesses a visitor can open. Distributors are advertising accounts.
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function directoryBusinesses(): Collection
    {
        return $this->businesses()->filter(fn (array $business): bool => $business['public'])->values();
    }

    /**
     * @return list<array{when: string, text: string, href: string}>
     */
    public function activity(): array
    {
        return $this->memo('activity', function (): array {
            $items = Event::query()
                ->with(['hostOrganisation', 'disciplines'])
                ->published()
                ->where('slug', 'not like', 'verify-%')
                ->latest('updated_at')
                ->limit(8)
                ->get()
                ->reject(fn (Event $event): bool => $this->isFixture($event->slug, $event->title))
                ->take(5)
                ->map(function (Event $event): array {
                    $host = $event->hostOrganisation?->name ?? 'An organiser';
                    $text = match (true) {
                        filled($event->results_url) => 'Results linked for '.$event->title,
                        $event->status === EventStatus::EntriesOpen => 'Registration is open for '.$event->title,
                        $event->status === EventStatus::Confirmed => $host.' confirmed '.$event->title,
                        default => $host.' listed '.$event->title,
                    };

                    return [
                        'when' => $event->updated_at?->timezone('Africa/Johannesburg')->format('j M') ?? '',
                        'text' => $text,
                        'href' => MockupUrl::to('mockups.match', ['slug' => $event->slug]),
                    ];
                })
                ->all();

            $range = Venue::query()
                ->published()
                ->where('slug', 'not like', 'verify-%')
                ->latest('created_at')
                ->first();

            if ($range !== null && ! $this->isFixture($range->slug, $range->name) && $range->created_at?->gt(now()->subDays(120))) {
                $items[] = [
                    'when' => $range->created_at->timezone('Africa/Johannesburg')->format('j M'),
                    'text' => 'Range added: '.$range->name,
                    'href' => MockupUrl::to('mockups.range', ['slug' => $range->slug]),
                ];
            }

            return array_slice($items, 0, 6);
        });
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    public function calendar(string $month, array $filters): array
    {
        $start = Carbon::createFromFormat('!Y-m-d', $month.'-01', 'Africa/Johannesburg');

        if ($start === false) {
            $start = now()->timezone('Africa/Johannesburg');
        }

        $start = $start->startOfMonth();
        $end = $start->copy()->endOfMonth();

        $events = Event::query()
            ->with(['disciplines', 'hostOrganisation.parent', 'venue', 'flags'])
            ->where('slug', 'not like', 'verify-%')
            ->where('status', '!=', EventStatus::Draft)
            ->where('status', '!=', EventStatus::Cancelled)
            ->whereBetween('starts_at', [$start->copy()->utc(), $end->copy()->utc()])
            ->orderBy('starts_at')
            ->get()
            ->reject(fn (Event $event): bool => $this->isFixture($event->slug, $event->title))
            ->map(fn (Event $event): array => $this->shapeEvent($event))
            ->values();

        $filtered = $this->filterMatches($events, $filters)['matches'];
        $byDate = $filtered->groupBy('date');

        $cursor = $start->copy()->startOfWeek(Carbon::MONDAY);
        $last = $end->copy()->endOfWeek(Carbon::SUNDAY);
        $weeks = [];

        while ($cursor->lte($last)) {
            $week = [];

            for ($day = 0; $day < 7; $day++) {
                $key = $cursor->toDateString();
                $week[] = [
                    'date' => $key,
                    'day' => $cursor->format('j'),
                    'in_month' => $cursor->month === $start->month,
                    'is_today' => $cursor->isSameDay(now()->timezone('Africa/Johannesburg')),
                    'events' => ($byDate->get($key) ?? collect())->values()->all(),
                ];
                $cursor->addDay();
            }

            $weeks[] = $week;
        }

        return [
            'label' => $start->format('F Y'),
            'month' => $start->format('Y-m'),
            'prev' => $start->copy()->subMonth()->format('Y-m'),
            'next' => $start->copy()->addMonth()->format('Y-m'),
            'today' => now()->timezone('Africa/Johannesburg')->format('Y-m'),
            'weeks' => $weeks,
            'agenda' => $filtered->values()->all(),
        ];
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $matches
     * @return array{markers: list<array<string, mixed>>, unlocated: list<array<string, mixed>>}
     */
    public function mapMarkers(Collection $matches): array
    {
        $located = $matches->filter(fn (array $match): bool => $match['has_gps'] && $match['lat'] !== null);
        $unlocated = $matches->reject(fn (array $match): bool => $match['has_gps'] && $match['lat'] !== null)->values();

        $markers = $located
            ->groupBy(fn (array $match): string => $match['range_slug'] ?: $match['lat'].','.$match['lng'])
            ->map(function (Collection $group): array {
                $first = $group->first();

                return [
                    'id' => $first['range_slug'] ?: md5($first['lat'].$first['lng']),
                    'name' => $first['range'] ?: $first['town'] ?: 'Match',
                    'place' => collect([$first['town'], $first['province']])->filter()->implode(', '),
                    'lat' => (float) $first['lat'],
                    'lng' => (float) $first['lng'],
                    'events' => $group->map(fn (array $match): array => [
                        'title' => $match['title'],
                        'slug' => $match['slug'],
                        'date_label' => $match['date_label'],
                        'discipline' => $match['discipline'],
                        'href' => MockupUrl::to('mockups.match', ['slug' => $match['slug']]),
                    ])->values()->all(),
                ];
            })
            ->values()
            ->all();

        return ['markers' => $markers, 'unlocated' => $unlocated->all()];
    }

    /**
     * @return array<string, mixed>|null
     */
    public function matchDetail(?string $slug): ?array
    {
        $slug ??= $this->samples()['match'];

        if ($slug === null) {
            return null;
        }

        $event = Event::query()
            ->with(['disciplines', 'hostOrganisation.disciplines', 'venue.disciplines', 'flags'])
            ->where('slug', $slug)
            ->first();

        if ($event === null || $this->isFixture($event->slug, $event->title)) {
            return null;
        }

        $match = $this->shapeEvent($event);
        $match['calendar_url'] = $event->starts_at ? $this->googleCalendarUrl($event) : null;
        $match['completeness'] = $this->completeness($match);
        $match['organiser_profile'] = $event->hostOrganisation ? $this->shapeClub($event->hostOrganisation) : null;

        if ($match['organiser_profile'] !== null) {
            $match['organiser_profile']['upcoming_count'] = $this->publicMatches()
                ->where('organiser_slug', $match['organiser_slug'])
                ->count();
        }
        $match['more_from_organiser'] = $this->publicMatches()
            ->filter(fn (array $row): bool => $row['organiser_slug'] === $match['organiser_slug'] && $row['slug'] !== $match['slug'])
            ->take(4)
            ->values()
            ->all();
        $match['similar'] = $this->publicMatches()
            ->filter(function (array $row) use ($match): bool {
                if ($row['slug'] === $match['slug']) {
                    return false;
                }

                return $row['discipline_slug'] === $match['discipline_slug']
                    || ($match['province_value'] !== null && $row['province_value'] === $match['province_value']);
            })
            ->take(4)
            ->values()
            ->all();

        $discipline = $event->primaryDiscipline() ?? $event->disciplines->first();
        $match['equipment'] = filled($discipline?->equipment_rules) ? $discipline->equipment_rules : null;
        $match['schema_gaps'] = ['Registration close is not stored on matches.'];

        return $match;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function sportDetail(?string $slug): ?array
    {
        $slug ??= $this->samples()['sport'];

        if ($slug === null) {
            return null;
        }

        $sport = Discipline::query()
            ->with(['federation', 'parent', 'children' => function (HasMany $query): void {
                $query->where('is_published', true)
                    ->orderBy('sort_order')
                    ->withCount([
                        'events as upcoming_count' => fn (Builder $events) => $events->upcoming(),
                    ]);
            }])
            ->where('slug', $slug)
            ->where('is_published', true)
            ->first();

        if ($sport === null || $this->isFixture($sport->slug, $sport->name)) {
            return null;
        }

        $row = $this->shapeSport($sport);
        $paragraphs = $this->paragraphs($sport->body);
        $beginner = collect($paragraphs)->first(fn (string $paragraph): bool => preg_match('/beginner|newcomer|new shooter/i', $paragraph) === 1);

        $fees = $sport->events()->upcoming()->whereNotNull('entry_fee_cents')->pluck('entry_fee_cents')
            ->map(fn (int $cents): string => (string) Money::rand($cents))
            ->unique()
            ->values()
            ->all();

        $row['paragraphs'] = $paragraphs;
        $row['beginner_paragraph'] = $beginner;
        $row['fees'] = $fees;
        $row['federation'] = $sport->federation ? [
            'name' => $sport->federation->name,
            'slug' => $sport->federation->slug,
        ] : null;
        $row['upcoming'] = $sport->events()->upcoming()->with(['disciplines', 'hostOrganisation', 'venue', 'flags'])->orderBy('starts_at')->limit(6)->get()
            ->reject(fn (Event $event): bool => $this->isFixture($event->slug, $event->title))
            ->map(fn (Event $event): array => $this->shapeEvent($event))
            ->values()
            ->all();
        $row['clubs'] = $sport->organisations()->clubs()->published()->orderBy('name')->limit(8)->get()
            ->reject(fn (Organisation $club): bool => $this->isFixture($club->slug, $club->name, $club->description))
            ->map(fn (Organisation $club): array => [
                'name' => $club->name,
                'slug' => $club->slug,
                'place' => $club->locationLabel(),
            ])
            ->all();
        $row['ranges'] = $sport->venues()->published()->orderBy('name')->limit(8)->get()
            ->reject(fn (Venue $range): bool => $this->isFixture($range->slug, $range->name))
            ->map(fn (Venue $range): array => [
                'name' => $range->name,
                'slug' => $range->slug,
                'place' => collect([$range->town, $range->province?->getLabel()])->filter()->implode(', '),
            ])
            ->all();
        $row['related'] = $this->sports()
            ->filter(fn (array $item): bool => $item['family'] === $row['family'] && $item['slug'] !== $row['slug'])
            ->take(5)
            ->values()
            ->all();

        return $row;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function clubDetail(?string $slug): ?array
    {
        $slug ??= $this->samples()['club'];

        if ($slug === null) {
            return null;
        }

        $club = Organisation::query()
            ->with([
                'disciplines',
                'hostedEvents' => fn ($query) => $query->with(['disciplines', 'hostOrganisation', 'venue', 'flags'])->orderBy('starts_at'),
            ])
            ->where('slug', $slug)
            ->first();

        if ($club === null || $this->isFixture($club->slug, $club->name, $club->description)) {
            return null;
        }

        $row = $this->shapeClub($club);
        $row['upcoming'] = $club->hostedEvents
            ->filter(fn (Event $event): bool => $event->starts_at !== null && $event->starts_at->gte(now()->subDay()) && $event->status !== EventStatus::Draft)
            ->take(8)
            ->map(fn (Event $event): array => $this->shapeEvent($event))
            ->values()
            ->all();
        $row['schema_gaps'] = [
            'Accepting new members is not stored.',
            'New shooters welcome is not stored separately from visitors welcome.',
        ];

        return $row;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function rangeDetail(?string $slug): ?array
    {
        $slug ??= $this->samples()['range'];

        if ($slug === null) {
            return null;
        }

        $range = Venue::query()
            ->with([
                'disciplines',
                'events' => fn ($query) => $query->upcoming()->with(['disciplines', 'hostOrganisation', 'venue', 'flags'])->orderBy('starts_at'),
            ])
            ->where('slug', $slug)
            ->first();

        if ($range === null || $this->isFixture($range->slug, $range->name)) {
            return null;
        }

        $row = $this->shapeRange($range);
        $row['upcoming'] = $range->events
            ->reject(fn (Event $event): bool => $this->isFixture($event->slug, $event->title))
            ->map(fn (Event $event): array => $this->shapeEvent($event))
            ->values()
            ->all();
        $row['schema_gaps'] = [
            'Operating days are not stored.',
            'Phone and email are not stored on ranges.',
        ];

        return $row;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function businessDetail(?string $slug): ?array
    {
        $slug ??= $this->samples()['business'];

        if ($slug === null) {
            return null;
        }

        $business = Provider::query()->with(['disciplines', 'placements.adSlot'])->where('slug', $slug)->first();

        if ($business === null || $this->isFixture($business->slug, $business->name, $business->description)) {
            return null;
        }

        $row = $this->shapeBusiness($business);
        $row['placements'] = $business->placements->map(fn (Placement $placement): array => [
            'name' => $placement->headline ?: ($placement->adSlot?->name ?: 'Placement'),
            'slot' => $placement->adSlot?->name,
        ])->all();

        return $row;
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    public function adminMatches(): Collection
    {
        return $this->memo('adminMatches', function (): Collection {
            return Event::query()
                ->with(['disciplines', 'hostOrganisation.parent', 'venue', 'flags'])
                ->where('slug', 'not like', 'verify-%')
                ->orderByDesc('starts_at')
                ->limit(80)
                ->get()
                ->reject(fn (Event $event): bool => $this->isFixture($event->slug, $event->title))
                ->map(function (Event $event): array {
                    $row = $this->shapeEvent($event);
                    $row['completeness'] = $this->completeness($row);
                    $row['quality'] = $row['completeness']['percent'] >= 80 ? 'Ready' : 'Incomplete';

                    return $row;
                })
                ->values();
        });
    }

    /**
     * @param  array<string, mixed>  $match
     * @return array{percent: int, missing: list<string>}
     */
    public function completeness(array $match): array
    {
        $checks = [
            'Match name' => filled($match['title'] ?? null),
            'Sport' => filled($match['discipline'] ?? null),
            'Level' => filled($match['level'] ?? null),
            'Description' => strlen((string) ($match['description'] ?? '')) > 40,
            'Date' => filled($match['date'] ?? null),
            'Start time' => ($match['time'] ?? null) !== null && ($match['time'] ?? '') !== '00:00',
            'Range' => filled($match['range'] ?? null),
            'Town or province' => filled($match['town'] ?? null) || filled($match['province'] ?? null),
            'Entry fee' => ($match['fee_cents'] ?? null) !== null,
            'Rounds or stages' => ($match['rounds'] ?? null) !== null || ($match['stages'] ?? null) !== null || ($match['targets'] ?? null) !== null,
            'Registration link' => filled($match['entry_url'] ?? null),
            'Organiser' => filled($match['organiser'] ?? null),
            // Not a column. Counted so the indicator matches the redesign, and stays honestly short.
            'Registration close' => false,
        ];

        $missing = collect($checks)->reject(fn (bool $ok): bool => $ok)->keys()->values()->all();
        $percent = (int) round((count($checks) - count($missing)) / count($checks) * 100);

        return ['percent' => $percent, 'missing' => $missing];
    }

    /**
     * @return array{summary: list<array<string, mixed>>, groups: list<array<string, mixed>>}
     */
    public function quality(): array
    {
        return $this->memo('quality', function (): array {
            $groups = [];

            $missingVenue = Event::query()->upcoming()->where('slug', 'not like', 'verify-%')->whereNull('venue_id')->limit(8)->get();
            $missingEntry = Event::query()->upcoming()->where('slug', 'not like', 'verify-%')->where(function (Builder $query): void {
                $query->whereNull('entry_url')->orWhere('entry_url', '');
            })->limit(8)->get();
            $missingSport = Event::query()->upcoming()->where('slug', 'not like', 'verify-%')->whereDoesntHave('disciplines')->limit(8)->get();
            $missingOrganiser = Event::query()->where('status', '!=', EventStatus::Draft)->where('slug', 'not like', 'verify-%')->whereNull('host_organisation_id')->limit(8)->get();

            $groups[] = [
                'title' => 'Matches',
                'items' => collect()
                    ->merge($this->eventIssues($missingVenue, 'critical', 'Missing range'))
                    ->merge($this->eventIssues($missingEntry, 'attention', 'Missing registration link'))
                    ->merge($this->eventIssues($missingSport, 'critical', 'Missing sport'))
                    ->merge($this->eventIssues($missingOrganiser, 'critical', 'Missing organiser'))
                    ->values()
                    ->all(),
            ];

            $clubs = Organisation::query()->clubs()->published()->where('slug', 'not like', 'verify-%')->with('disciplines')->withCount('hostedEvents')->get()
                ->reject(fn (Organisation $club): bool => $this->isFixture($club->slug, $club->name, $club->description));

            $clubItems = [];

            foreach ($clubs as $club) {
                if (! filled($club->email) && ! filled($club->phone)) {
                    $clubItems[] = $this->listingIssue('attention', $club->name, 'Missing contact', MockupUrl::to('mockups.club', ['slug' => $club->slug]));
                }

                if ($club->disciplines->isEmpty()) {
                    $clubItems[] = $this->listingIssue('attention', $club->name, 'No sports', MockupUrl::to('mockups.club', ['slug' => $club->slug]));
                }

                if ((int) $club->hosted_events_count === 0) {
                    $clubItems[] = $this->listingIssue('suggestion', $club->name, 'No matches on the register', MockupUrl::to('mockups.club', ['slug' => $club->slug]));
                }

                $stale = $club->verification_state !== VerificationState::Verified
                    || $club->last_verified_at === null
                    || $club->last_verified_at->lt(now()->subDays(180));

                if ($stale) {
                    $clubItems[] = $this->listingIssue('attention', $club->name, 'Not verified in the last six months', MockupUrl::to('mockups.club', ['slug' => $club->slug]));
                }
            }

            $groups[] = ['title' => 'Clubs', 'items' => array_slice($clubItems, 0, 12)];

            $ranges = Venue::query()->published()->where('slug', 'not like', 'verify-%')->get()
                ->reject(fn (Venue $range): bool => $this->isFixture($range->slug, $range->name));

            $rangeItems = [[
                'severity' => 'attention',
                'title' => 'Range records',
                'problem' => 'Phone and email are not stored on ranges',
                'href' => MockupUrl::to('mockups.admin.ranges'),
            ]];

            foreach ($ranges as $range) {
                if ($range->lat === null || $range->lng === null) {
                    $rangeItems[] = $this->listingIssue('critical', $range->name, 'Missing GPS', MockupUrl::to('mockups.range', ['slug' => $range->slug]));
                }

                if ($range->max_distance_m === null) {
                    $rangeItems[] = $this->listingIssue('suggestion', $range->name, 'Missing maximum distance', MockupUrl::to('mockups.range', ['slug' => $range->slug]));
                }
            }

            $groups[] = ['title' => 'Ranges', 'items' => array_slice($rangeItems, 0, 12)];

            $sports = Discipline::query()->where('is_published', true)->where('slug', 'not like', 'verify-%')->get()
                ->reject(fn (Discipline $sport): bool => $this->isFixture($sport->slug, $sport->name));

            $sportItems = [];

            foreach ($sports as $sport) {
                if (! filled($sport->body)) {
                    $sportItems[] = $this->listingIssue('suggestion', $sport->name, 'Missing longer description', MockupUrl::to('mockups.sport', ['slug' => $sport->slug]));
                }
            }

            if ($sportItems !== []) {
                $sportItems[] = $this->listingIssue('suggestion', 'Sport pages', 'No beginner-guide field — only free text, and most bodies are empty', MockupUrl::to('mockups.admin.sports'));
            }

            $groups[] = ['title' => 'Sports', 'items' => array_slice($sportItems, 0, 10)];

            $businesses = Provider::query()->where('slug', 'not like', 'verify-%')->get()
                ->reject(fn (Provider $business): bool => $this->isFixture($business->slug, $business->name, $business->description));

            $businessItems = [];

            foreach ($businesses as $business) {
                if ($business->category === null) {
                    $businessItems[] = $this->listingIssue('attention', $business->name, 'Missing category', MockupUrl::to('mockups.business', ['slug' => $business->slug]));
                }

                if ($business->verification_state !== VerificationState::Verified) {
                    $businessItems[] = $this->listingIssue('attention', $business->name, 'Unverified', MockupUrl::to('mockups.business', ['slug' => $business->slug]));
                }
            }

            $groups[] = ['title' => 'Industry', 'items' => $businessItems];

            $summary = [
                ['severity' => 'critical', 'count' => collect($groups)->flatMap(fn (array $group) => $group['items'])->where('severity', 'critical')->count(), 'label' => 'Critical'],
                ['severity' => 'attention', 'count' => collect($groups)->flatMap(fn (array $group) => $group['items'])->where('severity', 'attention')->count(), 'label' => 'Needs attention'],
                ['severity' => 'suggestion', 'count' => collect($groups)->flatMap(fn (array $group) => $group['items'])->where('severity', 'suggestion')->count(), 'label' => 'Suggestion'],
            ];

            return ['summary' => $summary, 'groups' => $groups];
        });
    }

    /**
     * @return array{pairs: list<array<string, mixed>>, coordinates: list<array<string, mixed>>}
     */
    public function duplicates(): array
    {
        return $this->memo('duplicates', function (): array {
            $pairs = array_merge(
                $this->namePairs($this->clubs(), 'Club'),
                $this->namePairs($this->ranges(), 'Range'),
                $this->namePairs($this->businesses(), 'Business'),
            );

            $coordinates = $this->ranges()
                ->filter(fn (array $range): bool => $range['lat'] !== null && $range['lng'] !== null)
                ->groupBy(fn (array $range): string => round((float) $range['lat'], 4).','.round((float) $range['lng'], 4))
                ->filter(fn (Collection $group): bool => $group->count() > 1)
                ->map(fn (Collection $group): array => [
                    'reason' => 'Same coordinates to four decimal places',
                    'records' => $group->pluck('name')->all(),
                ])
                ->values()
                ->all();

            return ['pairs' => $pairs, 'coordinates' => $coordinates];
        });
    }

    /**
     * @return array<string, mixed>
     */
    public function reports(): array
    {
        return $this->memo('reports', function (): array {
            $events = Event::query()
                ->with(['disciplines', 'venue'])
                ->where('slug', 'not like', 'verify-%')
                ->where('status', '!=', EventStatus::Draft)
                ->where('starts_at', '>=', now()->subMonths(6))
                ->get()
                ->reject(fn (Event $event): bool => $this->isFixture($event->slug, $event->title));

            $byMonth = $events->groupBy(fn (Event $event): string => $event->starts_at->timezone('Africa/Johannesburg')->format('Y-m'))
                ->map(fn (Collection $group, string $key): array => [
                    'label' => Carbon::createFromFormat('!Y-m-d', $key.'-01')->format('M Y'),
                    'count' => $group->count(),
                ])
                ->sortKeys()
                ->values()
                ->all();

            $bySport = $events->groupBy(fn (Event $event): string => ($event->primaryDiscipline() ?? $event->disciplines->first())?->name ?? 'Unassigned')
                ->map(fn (Collection $group, string $label): array => ['label' => $label, 'count' => $group->count()])
                ->sortByDesc('count')
                ->take(8)
                ->values()
                ->all();

            $byProvince = $events->groupBy(fn (Event $event): string => $event->venue?->province?->getLabel() ?? 'No province')
                ->map(fn (Collection $group, string $label): array => ['label' => $label, 'count' => $group->count()])
                ->sortByDesc('count')
                ->values()
                ->all();

            $placements = Placement::query()->get();

            return [
                'by_month' => $byMonth,
                'by_sport' => $bySport,
                'by_province' => $byProvince,
                'clubs' => $this->clubs()->count(),
                'ranges' => $this->ranges()->count(),
                'users' => User::query()->count(),
                'follows' => (int) Follow::query()->count(),
                'impressions' => (int) $placements->sum('impressions'),
                'clicks' => (int) $placements->sum('clicks'),
            ];
        });
    }

    /**
     * Published sports linked to a public division. Used after a visitor
     * picks Handgun, Shotgun, and so on, before they choose a discipline.
     *
     * @return list<string>
     */
    public function sportSlugsForDivision(Division $division): array
    {
        return $this->memo('division-sports-'.$division->value, function () use ($division): array {
            return Discipline::query()
                ->where('is_published', true)
                ->where('slug', 'not like', 'verify-%')
                ->whereHas('divisionLinks', fn (Builder $query) => $query->where('division', $division->value))
                ->pluck('slug')
                ->all();
        });
    }

    /**
     * The chosen sport, plus its published sub-disciplines, so a parent
     * still shows matches that are tagged on a child.
     *
     * @return list<string>|null
     */
    private function sportFilterSlugs(?string $slug): ?array
    {
        if ($slug === null) {
            return null;
        }

        $sport = $this->sports()->firstWhere('slug', $slug);
        $children = is_array($sport) ? array_column($sport['children'] ?? [], 'slug') : [];

        return array_values(array_unique(array_filter([$slug, ...$children])));
    }

    /**
     * Active supplier adverts. Read-only: this does not record impressions.
     *
     * @return Collection<int, array{headline: string, body: string, name: string, slug: string, category: ?string, place: string, image: ?string, discipline_slugs: list<string>, page: ?string, slot: ?string, division: ?string}>
     */
    public function sponsors(): Collection
    {
        return $this->memo('sponsors', function (): Collection {
            return Placement::query()
                ->with(['provider.disciplines', 'adSlot', 'disciplines'])
                ->activeOn()
                ->orderBy('id')
                ->get()
                ->map(function (Placement $placement): ?array {
                    $provider = $placement->provider;

                    if ($provider === null || $this->isFixture($provider->slug, $provider->name, $provider->description)) {
                        return null;
                    }

                    $row = $this->shapeBusiness($provider);

                    return [
                        'headline' => $placement->headline ?: $row['name'],
                        'body' => trim(strip_tags((string) $placement->body)),
                        'name' => $row['name'],
                        'slug' => $row['slug'],
                        'category' => $row['category'],
                        'place' => $row['place'],
                        'website' => $row['website'],
                        'public' => $row['public'],
                        'image' => $placement->imageUrl(),
                        'discipline_slugs' => $placement->disciplines->pluck('slug')->all(),
                        'page' => $placement->adSlot?->page?->value,
                        'slot' => $placement->adSlot?->slot?->value,
                        'division' => $placement->division?->value,
                    ];
                })
                ->filter()
                ->values();
        });
    }

    /**
     * Banners in the slot they were booked into.
     *
     * A division advert stays above that division's sport advert.
     * A calendar leaderboard stays on the unfiltered match list.
     * Sport adverts stay on that sport and its matches.
     *
     * @param  list<string>  $sportSlugs
     * @return Collection<int, array<string, mixed>>
     */
    public function sponsorStack(?Division $division = null, array $sportSlugs = []): Collection
    {
        $sportSlugs = array_values(array_filter($sportSlugs));
        $category = $this->sponsors()->filter(function (array $sponsor): bool {
            return ($sponsor['page'] ?? null) === AdPage::Disciplines->value
                && ($sponsor['slot'] ?? null) === PlacementSlot::CategorySponsor->value
                && filled($sponsor['image'] ?? null);
        })->values();

        if ($sportSlugs !== []) {
            $divisionValues = Discipline::query()
                ->whereIn('slug', $sportSlugs)
                ->with('divisionLinks')
                ->get()
                ->flatMap(fn (Discipline $sport): Collection => $sport->divisions())
                ->map(fn (Division $linked): string => $linked->value)
                ->unique()
                ->all();

            $divisionAds = $category->filter(function (array $sponsor) use ($divisionValues): bool {
                return ($sponsor['discipline_slugs'] ?? []) === []
                    && in_array($sponsor['division'] ?? null, $divisionValues, true);
            });
            $sportAds = $category->filter(function (array $sponsor) use ($sportSlugs): bool {
                return array_intersect($sponsor['discipline_slugs'] ?? [], $sportSlugs) !== [];
            });

            return $divisionAds->concat($sportAds)->unique('slug')->values();
        }

        if ($division instanceof Division) {
            return $category->filter(function (array $sponsor) use ($division): bool {
                return ($sponsor['discipline_slugs'] ?? []) === []
                    && ($sponsor['division'] ?? null) === $division->value;
            })->unique('slug')->values();
        }

        return $this->sponsors()->filter(function (array $sponsor): bool {
            return ($sponsor['page'] ?? null) === AdPage::Calendar->value
                && ($sponsor['slot'] ?? null) === PlacementSlot::Leaderboard->value
                && filled($sponsor['image'] ?? null)
                && ($sponsor['discipline_slugs'] ?? []) === []
                && ! filled($sponsor['division'] ?? null);
        })->unique('slug')->values();
    }

    /**
     * One screen's image banners.
     *
     * A banner stays on the page it was booked for. A sport-targeted
     * banner also stays on a match in that sport. Mailers stay out of the app.
     *
     * @param  list<string>  $sportSlugs
     * @return Collection<int, array<string, mixed>>
     */
    public function bannersFor(string $page, array $sportSlugs = []): Collection
    {
        $sportSlugs = array_values(array_filter($sportSlugs));

        return $this->sponsors()->filter(function (array $sponsor) use ($page, $sportSlugs): bool {
            if (! filled($sponsor['image'] ?? null) || ($sponsor['page'] ?? null) !== $page) {
                return false;
            }

            if (($sponsor['slot'] ?? null) === PlacementSlot::Mailer->value) {
                return false;
            }

            $targeted = $sponsor['discipline_slugs'] ?? [];

            if ($targeted === []) {
                return $page !== AdPage::Disciplines->value || $sportSlugs === [];
            }

            return $sportSlugs !== [] && array_intersect($targeted, $sportSlugs) !== [];
        })->unique('slug')->values();
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    public function placements(): Collection
    {
        return $this->memo('placements', fn (): Collection => Placement::query()
            ->with(['provider', 'adSlot'])
            ->orderByDesc('is_active')
            ->get()
            ->map(function (Placement $placement): array {
                $impressions = (int) $placement->impressions;
                $clicks = (int) $placement->clicks;

                return [
                    'id' => $placement->id,
                    'advertiser' => $placement->provider?->name ?? '—',
                    'headline' => $placement->headline ?: 'Untitled placement',
                    'placement' => $placement->adSlot?->name ?? ($placement->slot?->value ?? '—'),
                    'starts' => $placement->starts_on?->format('j M Y'),
                    'ends' => $placement->ends_on?->format('j M Y'),
                    'impressions' => $impressions,
                    'clicks' => $clicks,
                    'ctr' => $impressions > 0 ? round($clicks / $impressions * 100, 1).'%' : '—',
                    'status' => $placement->is_active ? 'Active' : 'Paused',
                ];
            }));
    }

    /**
     * @return list<array{when: string, text: string}>
     */
    public function dashboardActivity(): array
    {
        $week = $this->publicMatches()->filter(fn (array $match): bool => $match['date'] <= now()->addDays(7)->toDateString())->count();
        $month = $this->publicMatches()->filter(fn (array $match): bool => $match['date'] <= now()->addDays(30)->toDateString())->count();

        return [
            ['when' => 'This week', 'text' => $week.' upcoming '.($week === 1 ? 'match' : 'matches')],
            ['when' => '30 days', 'text' => $month.' upcoming '.($month === 1 ? 'match' : 'matches')],
        ];
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return Collection<int, array<string, mixed>>
     */
    public function filterClubs(array $filters): Collection
    {
        return $this->clubs()->filter(function (array $club) use ($filters): bool {
            $sport = $this->blank($filters['sport'] ?? null);
            $province = $this->blank($filters['province'] ?? null);
            $town = $this->blank($filters['town'] ?? null);

            if ($sport !== null && ! in_array($sport, $club['discipline_slugs'], true)) {
                return false;
            }

            if ($province !== null && $club['province_value'] !== $province) {
                return false;
            }

            if ($town !== null && ! str_contains(mb_strtolower((string) $club['town']), mb_strtolower($town))) {
                return false;
            }

            if (($filters['visitors'] ?? null) === '1' && ! $club['visitors_welcome']) {
                return false;
            }

            return true;
        })->values();
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array{ranges: Collection<int, array<string, mixed>>, hidden_for_distance: int}
     */
    public function filterRanges(array $filters): array
    {
        $hidden = 0;
        $ranges = $this->ranges()->filter(function (array $range) use ($filters, &$hidden): bool {
            $province = $this->blank($filters['province'] ?? null);
            $sport = $this->blank($filters['sport'] ?? null);
            $distance = $this->blank($filters['min_distance'] ?? null);
            $visitors = ($filters['visitors'] ?? null) === '1';
            $facility = $this->blank($filters['facility'] ?? null);

            if ($province !== null && $range['province_value'] !== $province) {
                return false;
            }

            if ($sport !== null && ! in_array($sport, $range['discipline_slugs'], true)) {
                return false;
            }

            if ($visitors && ! in_array($range['access_value'], ['public', 'guest_by_arrangement'], true)) {
                return false;
            }

            if ($facility !== null && ! in_array($facility, $range['facility_keys'], true)) {
                return false;
            }

            if ($distance !== null) {
                if ($range['max_distance_m'] === null) {
                    $hidden++;

                    return false;
                }

                if ((int) $range['max_distance_m'] < (int) $distance) {
                    return false;
                }
            }

            return true;
        })->values();

        return ['ranges' => $ranges, 'hidden_for_distance' => $hidden];
    }

    /**
     * @return array<string, mixed>
     */
    private function shapeEvent(Event $event): array
    {
        $starts = $event->starts_at?->timezone('Africa/Johannesburg');
        $discipline = $event->primaryDiscipline() ?? $event->disciplines->first();
        $venue = $event->venue;
        $badges = [];

        if (in_array('new-shooter-friendly', $event->flags->pluck('slug')->all(), true)) {
            $badges[] = ['label' => 'Beginner friendly', 'tone' => 'open'];
        }

        if ($event->status === EventStatus::EntriesOpen) {
            $badges[] = ['label' => 'Registration open', 'tone' => 'open'];
        } elseif ($event->created_at !== null && $event->created_at->gt(now()->subDays(14)) && count($badges) < 2) {
            $badges[] = ['label' => 'New', 'tone' => 'new'];
        }

        $badges = array_slice($badges, 0, 2);
        $distance = $venue?->max_distance_m !== null
            ? number_format((int) $venue->max_distance_m, 0, '.', ' ').' m'
            : ($discipline?->typical_distances ?: null);

        return [
            'title' => $event->title,
            'slug' => $event->slug,
            'description' => trim(strip_tags((string) $event->description)),
            'date' => $starts?->toDateString(),
            'date_label' => $starts ? $starts->format('j M Y') : '',
            'dow' => $starts ? EventDate::weekday($event->starts_at, $event->ends_at) : '',
            'day' => $starts ? EventDate::dayOfMonth($event->starts_at, $event->ends_at) : '',
            'month' => $starts ? EventDate::monthWithYear($event->starts_at, $event->ends_at) : '',
            'time' => $event->all_day ? 'All day' : $starts?->format('H:i'),
            'starts_at' => $event->starts_at?->toIso8601String(),
            'ends_at' => $event->ends_at?->timezone('Africa/Johannesburg')->format('j M Y H:i'),
            'discipline' => $discipline?->name,
            'discipline_slug' => $discipline?->slug,
            'discipline_slugs' => $event->disciplines->pluck('slug')->all(),
            'family' => $discipline?->family?->value,
            'level' => $event->level?->getLabel(),
            'level_value' => $event->level?->value,
            'status' => $event->status?->getLabel(),
            'status_value' => $event->status?->value,
            'organiser' => $event->hostOrganisation?->name,
            'organiser_slug' => $event->hostOrganisation?->slug,
            'organiser_logo' => $event->hostOrganisation?->logoUrl()
                ?? $event->hostOrganisation?->parent?->logoUrl(),
            'range' => $venue?->name,
            'range_slug' => $venue?->slug,
            'town' => $venue?->town,
            'province' => $venue?->province?->getLabel(),
            'province_value' => $venue?->province?->value,
            'address' => $venue?->address,
            'lat' => $venue?->lat !== null ? (float) $venue->lat : null,
            'lng' => $venue?->lng !== null ? (float) $venue->lng : null,
            'has_gps' => $venue?->lat !== null && $venue?->lng !== null,
            'directions' => $venue?->directionsUrl(),
            'fee' => Money::rand($event->entry_fee_cents),
            'fee_cents' => $event->entry_fee_cents,
            'member_fee' => Money::rand($event->member_fee_cents),
            'rounds' => $event->round_count,
            'stages' => $event->stage_count,
            'targets' => $event->target_count,
            'distance' => $distance,
            'entry_url' => $event->entry_url,
            'results_url' => $event->results_url,
            'flag_slugs' => $event->flags->pluck('slug')->all(),
            'flags' => $event->flags->pluck('name')->all(),
            'badges' => $badges,
            'created_at' => $event->created_at?->toIso8601String(),
            'capacity' => $event->capacity,
            'entries_taken' => $event->entries_taken,
            'distance_km' => null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function shapeSport(Discipline $sport): array
    {
        return [
            'name' => $sport->name,
            'slug' => $sport->slug,
            'family' => $sport->family?->value,
            'family_label' => $sport->family?->getLabel(),
            'blurb' => $sport->short_blurb,
            'body' => trim(strip_tags((string) $sport->body)),
            'distances' => $sport->typical_distances,
            'equipment' => filled($sport->equipment_rules) ? $sport->equipment_rules : null,
            'clubs_count' => (int) ($sport->clubs_count ?? $sport->organisations()->count()),
            'ranges_count' => (int) ($sport->ranges_count ?? 0),
            'upcoming_count' => (int) ($sport->upcoming_count ?? 0),
            'parent_slug' => $sport->relationLoaded('parent') ? $sport->parent?->slug : null,
            'parent_name' => $sport->relationLoaded('parent') ? $sport->parent?->name : null,
            'children' => $sport->relationLoaded('children')
                ? $sport->children->map(fn (Discipline $child): array => [
                    'name' => $child->name,
                    'slug' => $child->slug,
                    'blurb' => $child->short_blurb,
                    'upcoming_count' => (int) ($child->upcoming_count ?? 0),
                ])->values()->all()
                : [],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function shapeClub(Organisation $club): array
    {
        $events = $club->relationLoaded('hostedEvents') ? $club->hostedEvents : collect();
        $venue = $events->first(fn (Event $event): bool => $event->venue !== null)?->venue;

        return [
            'name' => $club->name,
            'slug' => $club->slug,
            'type' => $club->type?->getLabel(),
            'town' => $club->town,
            'province' => $club->province?->getLabel(),
            'province_value' => $club->province?->value,
            'place' => $club->locationLabel(),
            'description' => trim(strip_tags((string) $club->description)),
            'disciplines' => $club->disciplines->pluck('name')->all(),
            'discipline_slugs' => $club->disciplines->pluck('slug')->all(),
            'upcoming_count' => $club->relationLoaded('hostedEvents')
                ? $events->filter(fn (Event $event): bool => $event->starts_at?->gte(now()->subDay()) ?? false)->count()
                : (int) ($club->upcoming_count ?? 0),
            'range' => $venue?->name,
            'range_slug' => $venue?->slug,
            'visitors_welcome' => (bool) $club->visitors_welcome,
            'website' => $club->website_url,
            'logo' => $club->logoUrl() ?? $club->parent?->logoUrl(),
            'email' => $club->email,
            'phone' => $club->phone,
            'facebook' => $club->facebook_url,
            'verified' => $club->verification_state === VerificationState::Verified,
            'verification' => $club->verification_state?->getLabel(),
            'is_stale' => $club->updated_at === null || $club->updated_at->lt(now()->subDays(180)),
            'updated' => $club->updated_at?->timezone('Africa/Johannesburg')->format('j M Y'),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function shapeRange(Venue $range): array
    {
        $facilities = collect($range->facilities ?? [])
            ->filter(fn (mixed $value): bool => $value === true || $value === 1 || $value === '1')
            ->keys()
            ->map(fn (string $key): string => str($key)->replace('_', ' ')->title()->toString())
            ->values()
            ->all();

        $events = $range->relationLoaded('events') ? $range->events : collect();
        $clubs = $events->map(fn (Event $event): ?string => $event->hostOrganisation?->name)->filter()->unique()->values()->all();

        return [
            'name' => $range->name,
            'slug' => $range->slug,
            'town' => $range->town,
            'province' => $range->province?->getLabel(),
            'province_value' => $range->province?->value,
            'place' => collect([$range->town, $range->province?->getLabel()])->filter()->implode(', '),
            'address' => $range->address,
            'lat' => $range->lat !== null ? (float) $range->lat : null,
            'lng' => $range->lng !== null ? (float) $range->lng : null,
            'has_gps' => $range->lat !== null && $range->lng !== null,
            'max_distance_m' => $range->max_distance_m,
            'max_distance' => $range->max_distance_m !== null ? number_format((int) $range->max_distance_m, 0, '.', ' ').' m' : null,
            'access' => $range->access?->getLabel(),
            'access_value' => $range->access?->value,
            'disciplines' => $range->disciplines->pluck('name')->all(),
            'discipline_slugs' => $range->disciplines->pluck('slug')->all(),
            'facilities' => $facilities,
            'facility_keys' => collect($range->facilities ?? [])->filter(fn (mixed $value): bool => (bool) $value)->keys()->all(),
            'notes' => trim(strip_tags((string) $range->notes)),
            'upcoming_count' => $events->count(),
            'clubs' => $clubs,
            'directions' => $range->directionsUrl(),
            'day_fee' => Money::rand($range->day_fee_cents),
            'bays' => $range->bay_count,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function shapeBusiness(Provider $business): array
    {
        $services = collect($business->services ?? [])->filter(fn (mixed $service): bool => is_string($service) && $service !== '')->values()->all();

        return [
            'name' => $business->name,
            'slug' => $business->slug,
            'category' => $business->category?->getLabel(),
            'category_value' => $business->category?->value,
            'public' => $business->category === null || $business->category->isPublic(),
            'services' => $services,
            'town' => $business->town,
            'province' => $business->province?->getLabel(),
            'province_value' => $business->province?->value,
            'place' => collect([$business->town, $business->province?->getLabel()])->filter()->implode(', '),
            'description' => trim(strip_tags((string) $business->description)),
            'website' => $business->website_url,
            'email' => $business->email,
            'phone' => $business->phone,
            'disciplines' => $business->relationLoaded('disciplines') ? $business->disciplines->pluck('name')->all() : [],
            'tier' => $business->tier?->value,
            'tier_label' => $business->tier?->getLabel(),
            'verified' => $business->verification_state === VerificationState::Verified,
            'featured' => $business->tier?->value === 'featured',
            'sponsored' => $business->tier?->value === 'featured',
        ];
    }

    private function googleCalendarUrl(Event $event): string
    {
        $start = $event->starts_at->timezone('Africa/Johannesburg');
        $end = $event->ends_at?->timezone('Africa/Johannesburg') ?? $start->copy()->addHours(8);

        $dates = $event->all_day
            ? $start->format('Ymd').'/'.$end->copy()->addDay()->format('Ymd')
            : $start->copy()->utc()->format('Ymd\THis\Z').'/'.$end->copy()->utc()->format('Ymd\THis\Z');

        $location = collect([$event->venue?->name, $event->venue?->town, $event->venue?->province?->getLabel()])->filter()->implode(', ');

        return 'https://calendar.google.com/calendar/render?action=TEMPLATE&text='.urlencode($event->title).'&dates='.$dates.'&location='.urlencode($location);
    }

    /**
     * @param  Collection<int, Event>  $events
     * @return Collection<int, array<string, mixed>>
     */
    private function eventIssues(Collection $events, string $severity, string $problem): Collection
    {
        return $events
            ->reject(fn (Event $event): bool => $this->isFixture($event->slug, $event->title))
            ->map(fn (Event $event): array => $this->listingIssue($severity, $event->title, $problem, MockupUrl::to('mockups.admin.matches.edit', ['slug' => $event->slug])));
    }

    /**
     * @return array{severity: string, title: string, problem: string, href: string}
     */
    private function listingIssue(string $severity, string $title, string $problem, string $href): array
    {
        return compact('severity', 'title', 'problem', 'href');
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $rows
     * @return list<array<string, mixed>>
     */
    private function namePairs(Collection $rows, string $kind): array
    {
        $items = $rows->values();
        $pairs = [];

        for ($i = 0; $i < $items->count(); $i++) {
            for ($j = $i + 1; $j < $items->count(); $j++) {
                similar_text(mb_strtolower($items[$i]['name']), mb_strtolower($items[$j]['name']), $percent);

                if ($percent >= 72 && $percent < 100) {
                    $pairs[] = [
                        'kind' => $kind,
                        'left' => $items[$i]['name'],
                        'right' => $items[$j]['name'],
                        'left_href' => MockupUrl::to('mockups.'.strtolower($kind), ['slug' => $items[$i]['slug']]),
                        'right_href' => MockupUrl::to('mockups.'.strtolower($kind), ['slug' => $items[$j]['slug']]),
                        'reason' => 'Names are '.round($percent).'% similar',
                    ];
                }
            }
        }

        return $pairs;
    }

    /**
     * @return list<string>
     */
    private function paragraphs(?string $body): array
    {
        $text = trim(strip_tags((string) $body));

        if ($text === '') {
            return [];
        }

        return collect(preg_split("/\n\s*\n/", $text) ?: [])
            ->map(fn (string $paragraph): string => trim($paragraph))
            ->filter()
            ->values()
            ->all();
    }

    private function kilometres(?float $lat1, ?float $lng1, float $lat2, float $lng2): ?float
    {
        if ($lat1 === null || $lng1 === null) {
            return null;
        }

        $earth = 6371;
        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);
        $a = sin($dLat / 2) ** 2 + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLng / 2) ** 2;

        return $earth * (2 * atan2(sqrt($a), sqrt(1 - $a)));
    }

    private function isFixture(?string $slug, ?string $name, ?string $description = null): bool
    {
        if (str_starts_with((string) $slug, 'verify-') || str_starts_with((string) $name, 'Verify ')) {
            return true;
        }

        return $description !== null && str_contains($description, 'Iusto quia');
    }

    private function blank(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $value = trim($value);

        return $value === '' ? null : $value;
    }

    private function memo(string $key, callable $load): mixed
    {
        if (! array_key_exists($key, $this->memo)) {
            $this->memo[$key] = $load();
        }

        return $this->memo[$key];
    }
}
