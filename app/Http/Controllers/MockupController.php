<?php

namespace App\Http\Controllers;

use App\Enums\Division;
use App\Enums\Province;
use App\Services\StartProTrial;
use App\Support\Geo;
use App\Support\Mockups\MockupCatalog;
use App\Support\Mockups\MockupUrl;
use App\Support\Mockups\PackingKits;
use App\Support\ThisWeekend;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * Redesign mockups. Read-only. These routes do not replace production pages.
 */
class MockupController extends Controller
{
    public function __construct(private MockupCatalog $catalog)
    {
        view()->composer(['mockups.*', 'components.mockups.*'], function ($view): void {
            $view->with('mk', fn (string $name, array $parameters = [], bool $preserveDevice = true): string => MockupUrl::to($name, $parameters, $preserveDevice));
            $view->with(
                'cartoApiKey',
                filled(config('services.carto.api_key')) ? (string) config('services.carto.api_key') : null,
            );
        });
    }

    public function index(): View
    {
        return view('mockups.index', [
            'samples' => $this->catalog->samples(),
        ]);
    }

    public function home(): View
    {
        [$weekendStart, $weekendEnd] = ThisWeekend::range();

        return view('mockups.home', [
            'stats' => $this->catalog->stats(),
            'matches' => $this->catalog->publicMatches()->take(6),
            'divisions' => Division::publicCases(),
            'weekendFrom' => $weekendStart->toDateString(),
            'weekendTo' => $weekendEnd->toDateString(),
            'weekendMonth' => $weekendStart->format('Y-m'),
            'provinces' => Province::cases(),
            'sportOptions' => $this->catalog->sports(),
        ]);
    }

    public function matches(Request $request): View
    {
        $result = $this->catalog->filterMatches($this->catalog->publicMatches(), $request->query());
        $page = max(1, $request->integer('page', 1));
        $perPage = 12;
        $matches = $result['matches'];
        $division = Division::fromPublicQuery($request->string('division')->toString());
        $sportFilter = $this->selectedSport($request);
        $pageMatches = $matches->forPage($page, $perPage)->values();

        return view('mockups.matches', [
            'matches' => $pageMatches,
            'total' => $matches->count(),
            'page' => $page,
            'lastPage' => max(1, (int) ceil($matches->count() / $perPage)),
            'unlocated' => $result['unlocated'],
            'sports' => $this->catalog->sports(),
            'provinces' => Province::cases(),
            'sponsors' => $this->catalog->sponsorStack($division, $this->sportSlugs($sportFilter)),
            'division' => $division,
            'divisionSports' => $division instanceof Division ? $this->catalog->sportSlugsForDivision($division) : [],
            'sportFilter' => $sportFilter,
            'sportSlugs' => $this->sportSlugs($sportFilter),
        ]);
    }

    public function calendar(Request $request): View
    {
        $month = $request->string('month')->toString();

        if (! preg_match('/^\d{4}-\d{2}$/', $month)) {
            $month = now()->timezone('Africa/Johannesburg')->format('Y-m');
        }

        $division = Division::fromPublicQuery($request->string('division')->toString());
        $sportFilter = $this->selectedSport($request);

        $calendar = $this->catalog->calendar($month, $request->query());

        return view('mockups.calendar', [
            'calendar' => $calendar,
            'sports' => $this->catalog->sports(),
            'provinces' => Province::cases(),
            'sponsors' => $this->catalog->sponsorStack($division, $this->sportSlugs($sportFilter)),
            'division' => $division,
            'divisionSports' => $division instanceof Division ? $this->catalog->sportSlugsForDivision($division) : [],
            'sportFilter' => $sportFilter,
            'sportSlugs' => $this->sportSlugs($sportFilter),
        ]);
    }

    public function map(Request $request): View
    {
        $result = $this->catalog->filterMatches($this->catalog->publicMatches(), $request->query());
        $mapped = $this->catalog->mapMarkers($result['matches']);
        $division = Division::fromPublicQuery($request->string('division')->toString());
        $sportFilter = $this->selectedSport($request);

        return view('mockups.map', [
            'markers' => $mapped['markers'],
            'unlocated' => $mapped['unlocated'],
            'total' => $result['matches']->count(),
            'sports' => $this->catalog->sports(),
            'provinces' => Province::cases(),
            'sponsors' => $this->catalog->sponsorStack($division, $this->sportSlugs($sportFilter)),
        ]);
    }

    public function match(?string $slug = null): View
    {
        $match = $this->catalog->matchDetail($slug);

        abort_if($slug !== null && $match === null, 404);

        return view('mockups.match', [
            'match' => $match,
            'choices' => $this->catalog->publicMatches()->take(12),
            'sponsors' => $match
                ? $this->catalog->sponsorStack(null, $match['discipline_slugs'] ?? [])
                : collect(),
        ]);
    }

    public function sports(Request $request): View
    {
        $q = trim($request->string('q')->toString());
        $family = trim($request->string('family')->toString());
        $division = Division::fromPublicQuery($request->string('division')->toString());
        $divisionSlugs = $division instanceof Division ? $this->catalog->sportSlugsForDivision($division) : null;
        $sports = $this->catalog->sports()->filter(function (array $sport) use ($q, $family, $division, $divisionSlugs): bool {
            if ($division instanceof Division) {
                if (($sport['parent_slug'] ?? null) !== null || ! in_array($sport['slug'], $divisionSlugs ?? [], true)) {
                    return false;
                }
            }

            if ($family !== '' && $sport['family'] !== $family) {
                return false;
            }

            if ($q === '') {
                return true;
            }

            $haystack = mb_strtolower($sport['name'].' '.$sport['blurb']);

            return str_contains($haystack, mb_strtolower($q));
        })->values();

        return view('mockups.sports', [
            'sports' => $sports,
            'division' => $division,
            'sponsors' => $division instanceof Division
                ? $this->catalog->sponsorStack($division)
                : collect(),
            'divisionSports' => $divisionSlugs ?? [],
        ]);
    }

    public function sport(Request $request, ?string $slug = null): View
    {
        $sport = $this->catalog->sportDetail($slug);

        abort_if($slug !== null && $sport === null, 404);

        $sportSlugs = $sport === null ? [] : array_values(array_unique(array_filter([
            $sport['slug'],
            ...array_column($sport['children'] ?? [], 'slug'),
        ])));

        return view('mockups.sport', [
            'sport' => $sport,
            'choices' => $this->catalog->sports(),
            'sponsors' => $this->catalog->sponsorStack(null, $sportSlugs),
            'follows' => $this->readFollows($request),
        ]);
    }

    public function clubs(Request $request): View
    {
        $members = $request->query('members') === '1';

        return view('mockups.clubs', [
            'clubs' => $members ? collect() : $this->catalog->filterClubs($request->query()),
            'sports' => $this->catalog->sports(),
            'provinces' => Province::cases(),
            'membersFilterUnused' => $request->query('members') === '1',
        ]);
    }

    public function club(Request $request, ?string $slug = null): View
    {
        $club = $this->catalog->clubDetail($slug);

        abort_if($slug !== null && $club === null, 404);

        return view('mockups.club', [
            'club' => $club,
            'choices' => $this->catalog->clubs(),
            'follows' => $this->readFollows($request),
        ]);
    }

    public function ranges(Request $request): View
    {
        $result = $this->catalog->filterRanges($request->query());

        return view('mockups.ranges', [
            'ranges' => $result['ranges'],
            'hiddenForDistance' => $result['hidden_for_distance'],
            'sports' => $this->catalog->sports(),
            'provinces' => Province::cases(),
            'view' => $request->query('view') === 'map' ? 'map' : 'list',
        ]);
    }

    public function range(?string $slug = null): View
    {
        $range = $this->catalog->rangeDetail($slug);

        abort_if($slug !== null && $range === null, 404);

        return view('mockups.range', [
            'range' => $range,
            'choices' => $this->catalog->ranges()->take(12),
        ]);
    }

    public function industry(Request $request): View
    {
        $q = trim($request->string('q')->toString());
        $category = trim($request->string('category')->toString());
        $province = trim($request->string('province')->toString());
        $businesses = $this->catalog->directoryBusinesses()->filter(function (array $business) use ($q, $category, $province): bool {
            if ($category !== '' && $business['category_value'] !== $category) {
                return false;
            }

            if ($province !== '' && $business['province_value'] !== $province) {
                return false;
            }

            if ($q === '') {
                return true;
            }

            return str_contains(mb_strtolower($business['name'].' '.$business['description'].' '.$business['category']), mb_strtolower($q));
        })->values();

        return view('mockups.industry', [
            'businesses' => $businesses,
            'categories' => $this->catalog->directoryBusinesses()->pluck('category', 'category_value')->filter(),
            'provinces' => Province::cases(),
        ]);
    }

    public function business(?string $slug = null): View
    {
        $business = $this->catalog->businessDetail($slug);

        abort_if($slug !== null && $business === null, 404);

        return view('mockups.business', [
            'business' => $business,
            'choices' => $this->catalog->directoryBusinesses(),
        ]);
    }

    public function search(Request $request): View
    {
        $q = trim($request->string('q')->toString());
        $match = function (string $haystack) use ($q): bool {
            return $q === '' || str_contains(mb_strtolower($haystack), mb_strtolower($q));
        };

        $groups = [
            'Matches' => $this->catalog->publicMatches()->filter(fn (array $row): bool => $match($row['title'].' '.$row['discipline'].' '.$row['town'].' '.$row['province']))->take($q === '' ? 4 : 12)->values(),
            'Sports' => $this->catalog->sports()->filter(fn (array $row): bool => $match($row['name'].' '.$row['blurb']))->take($q === '' ? 4 : 12)->values(),
            'Clubs' => $this->catalog->clubs()->filter(fn (array $row): bool => $match($row['name'].' '.$row['place']))->take($q === '' ? 4 : 12)->values(),
            'Ranges' => $this->catalog->ranges()->filter(fn (array $row): bool => $match($row['name'].' '.$row['place']))->take($q === '' ? 4 : 12)->values(),
            'Industry' => $this->catalog->directoryBusinesses()->filter(fn (array $row): bool => $match($row['name'].' '.$row['category'].' '.$row['description']))->take($q === '' ? 4 : 12)->values(),
        ];

        return view('mockups.search', [
            'q' => $q,
            'groups' => $groups,
        ]);
    }

    public function account(Request $request): View
    {
        $sports = collect($request->query('sport', []))->filter(fn (mixed $slug): bool => is_string($slug))->values();
        $clubs = collect($request->query('club', []))->filter(fn (mixed $slug): bool => is_string($slug))->values();
        $province = trim($request->string('province')->toString());
        $hasFollows = $sports->isNotEmpty() || $clubs->isNotEmpty() || $province !== '';

        $coming = $this->catalog->publicMatches()->filter(function (array $match) use ($sports, $clubs, $province, $hasFollows): bool {
            if (! $hasFollows) {
                return false;
            }

            $sportHit = $sports->isNotEmpty() && $sports->intersect($match['discipline_slugs'])->isNotEmpty();
            $clubHit = $clubs->isNotEmpty() && $clubs->contains($match['organiser_slug']);
            $provinceHit = $province !== '' && $match['province_value'] === $province;

            return $sportHit || $clubHit || $provinceHit;
        })->take(8)->values();

        $preview = $this->catalog->publicMatches()
            ->groupBy('discipline_slug')
            ->sortByDesc(fn ($group) => $group->count())
            ->keys()
            ->first();

        return view('mockups.account', [
            'follows' => [
                'sports' => $sports,
                'clubs' => $clubs,
                'province' => $province,
            ],
            'coming' => $coming,
            'hasFollows' => $hasFollows,
            'sportOptions' => $this->catalog->sports(),
            'clubOptions' => $this->catalog->clubs(),
            'previewSport' => $preview,
            'activity' => $this->catalog->activity(),
        ]);
    }

    public function onboarding(): View
    {
        return view('mockups.onboarding', [
            'sports' => $this->catalog->sports(),
            'clubs' => $this->catalog->clubs(),
            'provinces' => Province::cases(),
        ]);
    }

    public function following(Request $request): View
    {
        $sports = collect($request->query('sport', []))->filter(fn (mixed $slug): bool => is_string($slug))->values();
        $clubs = collect($request->query('club', []))->filter(fn (mixed $slug): bool => is_string($slug))->values();
        $province = trim($request->string('province')->toString());

        return view('mockups.following', [
            'follows' => [
                'sports' => $sports,
                'clubs' => $clubs,
                'province' => $province,
            ],
            'sportOptions' => $this->catalog->sports(),
            'clubOptions' => $this->catalog->clubs(),
            'provinces' => Province::cases(),
        ]);
    }

    public function apps(Request $request): View
    {
        $allowed = [
            // First run
            'splash', 'welcome', 'location', 'sports-choose', 'follow-onboard', 'alerts-onboard', 'ready',
            // Home & discovery
            'home', 'today', 'matches', 'calendar', 'search',
            // Match journey + packing
            'match', 'pack', 'packing',
            // Explore
            'find', 'clubs', 'club', 'ranges', 'range', 'sports', 'sport',
            // Suppliers
            'suppliers', 'supplier',
            // Account
            'you', 'following', 'appearance', 'alerts',
            // Subscription
            'subscription', 'cancel', 'cancelled', 'plans',
            // Privacy
            'data', 'delete', 'deleted',
            // States
            'permissions', 'signin',
        ];
        $screen = $request->string('screen')->toString();

        if (! in_array($screen, $allowed, true)) {
            $screen = 'home';
        }

        // Legacy alias: `today` was the match list.
        if ($screen === 'today') {
            $screen = 'matches';
        }

        $upcoming = $this->catalog->publicMatches();
        $close = $this->closeFilter($request);
        $slug = $request->string('match')->toString();
        $matches = $this->withinReach($upcoming, $close)->take(20)->values();
        $match = $slug !== '' ? $upcoming->firstWhere('slug', $slug) : $matches->first();
        $clubs = $this->catalog->clubs();
        $ranges = $this->catalog->ranges();
        $sports = $this->catalog->sports();
        $businesses = $this->catalog->directoryBusinesses();
        $placeValues = $upcoming->pluck('province_value')
            ->merge($clubs->pluck('province_value'))
            ->merge($ranges->pluck('province_value'))
            ->merge($businesses->pluck('province_value'))
            ->filter()
            ->unique();
        $places = collect(Province::cases())
            ->filter(fn (Province $province): bool => $placeValues->contains($province->value))
            ->values();
        $clubSlug = $request->string('club')->toString();
        $rangeSlug = $request->string('range')->toString();
        $sportSlug = $request->string('sport')->toString();
        $openSport = $sportSlug !== '' ? $sports->firstWhere('slug', $sportSlug) : $sports->first();

        return view('mockups.apps', [
            'screen' => $screen,
            'journey' => $this->appJourney(),
            'screens' => [
                'splash' => 'Splash',
                'welcome' => 'Welcome',
                'location' => 'Location',
                'sports-choose' => 'Sports',
                'follow-onboard' => 'Follow',
                'alerts-onboard' => 'Alerts',
                'ready' => 'Ready',
                'home' => 'Home',
                'matches' => 'Matches',
                'calendar' => 'Calendar',
                'search' => 'Search',
                'match' => 'Match',
                'pack' => 'Pack',
                'packing' => 'Packing',
                'find' => 'Find',
                'clubs' => 'Clubs',
                'club' => 'Club',
                'ranges' => 'Ranges',
                'range' => 'Range',
                'sports' => 'Sports',
                'sport' => 'Sport',
                'suppliers' => 'Suppliers',
                'supplier' => 'Supplier',
                'you' => 'You',
                'following' => 'Following',
                'appearance' => 'Appearance',
                'alerts' => 'Alerts',
                'subscription' => 'Pro',
                'cancel' => 'Cancel',
                'cancelled' => 'Cancelled',
                'plans' => 'Before you pay',
                'data' => 'Your data',
                'delete' => 'Delete account',
                'permissions' => 'Permissions',
                'signin' => 'Sign in',
            ],
            'cycle' => $request->string('cycle')->toString() === 'monthly' ? 'monthly' : 'annual',
            'paused' => $request->query('emails') === 'off',
            'restored' => $request->query('restored') === '1',
            'close' => $close,
            'saved' => $request->query('saved') === '1',
            'started' => $request->query('started') === '1',
            'matches' => $matches,
            'match' => $match,
            'eventSearch' => $this->withCloseResults($this->eventSearch($request, $upcoming, $sports, $clubs), $close),
            'stats' => $this->catalog->stats(),
            'places' => $places,
            'clubs' => $this->withinPlace($clubs, $close),
            'club' => $clubSlug !== '' ? $clubs->firstWhere('slug', $clubSlug) : $this->withinPlace($clubs, $close)->first(),
            'ranges' => $this->withinPlace($ranges, $close),
            'range' => $rangeSlug !== '' ? $ranges->firstWhere('slug', $rangeSlug) : $this->withinPlace($ranges, $close)->first(),
            'sports' => $sports,
            'sport' => $openSport,
            'sportMatches' => $this->withinReach(
                $upcoming->filter(fn (array $row): bool => $openSport !== null && in_array($openSport['slug'], $row['discipline_slugs'], true))->values(),
                $close,
            ),
            'businesses' => $this->withinPlace($businesses, $close),
            'sponsors' => $this->catalog->sponsors(),
            'bannersFor' => fn (string $page, array $sportSlugs = []): Collection => $this->catalog->bannersFor($page, $sportSlugs),
            'supplier' => $screen === 'supplier'
                ? $this->catalog->businessDetail($request->string('supplier')->toString() ?: null)
                : null,
            'kits' => $sports->map(fn (array $row): array => [
                'name' => $row['name'],
                'slug' => $row['slug'],
                'family' => $row['family_label'] ?: 'Sport',
                'count' => count(PackingKits::basics($row['slug'], $row['family'])),
            ])->values(),
            'kit' => $this->packingKit(
                $screen === 'pack' && $sportSlug !== '' && ! $request->filled('match')
                    ? $sports->firstWhere('slug', $sportSlug)
                    : null,
                $upcoming,
            ),
            'packing' => $screen === 'pack' && $request->filled('match')
                ? $this->packingSession($request, $upcoming->firstWhere('slug', $slug))
                : null,
            'sportMissing' => $screen === 'pack' && $sportSlug !== '' && ! $request->filled('match') && $sports->firstWhere('slug', $sportSlug) === null,
            'pricing' => config('plans.pricing'),
            'renews' => now()->timezone('Africa/Johannesburg')->addMonth()->format('j M Y'),
            'trialDays' => StartProTrial::TRIAL_DAYS,
            'phoneCalendar' => $screen === 'calendar' ? $this->appCalendar($request, $close) : null,
            'home' => in_array($screen, ['home', 'welcome'], true) ? $this->appHome($matches, $this->withinPlace($clubs, $close), $close) : null,
            'onboarding' => in_array($screen, ['sports-choose', 'follow-onboard', 'ready'], true)
                ? $this->appOnboarding($request, $sports, $clubs)
                : null,
            'appearanceTheme' => $request->query('theme') === 'light' ? 'light' : 'dark',
            'appearanceLarge' => $request->query('type') === 'large',
        ]);
    }

    /**
     * Ordered journey groups for the review sidebar.
     *
     * @return list<array{group: string, screens: list<array{key: string, label: string, params?: array<string, string>}>}>
     */
    private function appJourney(): array
    {
        return [
            [
                'group' => '01 · First run',
                'screens' => [
                    ['key' => 'splash', 'label' => 'Splash'],
                    ['key' => 'welcome', 'label' => 'Welcome'],
                    ['key' => 'location', 'label' => 'Location'],
                    ['key' => 'sports-choose', 'label' => 'Choose sports'],
                    ['key' => 'follow-onboard', 'label' => 'Follow'],
                    ['key' => 'alerts-onboard', 'label' => 'Alerts'],
                    ['key' => 'ready', 'label' => 'Ready'],
                ],
            ],
            [
                'group' => '02 · Home & discovery',
                'screens' => [
                    ['key' => 'home', 'label' => 'Home'],
                    ['key' => 'matches', 'label' => 'Matches'],
                    ['key' => 'calendar', 'label' => 'Calendar'],
                    ['key' => 'search', 'label' => 'Search'],
                ],
            ],
            [
                'group' => '03 · Match journey',
                'screens' => [
                    ['key' => 'match', 'label' => 'Match detail'],
                    ['key' => 'pack', 'label' => 'Pack for match'],
                ],
            ],
            [
                'group' => '04 · Packing',
                'screens' => [
                    ['key' => 'packing', 'label' => 'Packing library'],
                ],
            ],
            [
                'group' => '05 · Explore',
                'screens' => [
                    ['key' => 'find', 'label' => 'Find'],
                    ['key' => 'clubs', 'label' => 'Clubs'],
                    ['key' => 'club', 'label' => 'Club detail'],
                    ['key' => 'ranges', 'label' => 'Ranges'],
                    ['key' => 'range', 'label' => 'Range detail'],
                    ['key' => 'sports', 'label' => 'Sports'],
                    ['key' => 'sport', 'label' => 'Sport detail'],
                ],
            ],
            [
                'group' => '06 · Suppliers',
                'screens' => [
                    ['key' => 'suppliers', 'label' => 'Suppliers'],
                    ['key' => 'supplier', 'label' => 'Supplier detail'],
                ],
            ],
            [
                'group' => '07 · Account',
                'screens' => [
                    ['key' => 'you', 'label' => 'You'],
                    ['key' => 'following', 'label' => 'Following'],
                    ['key' => 'alerts', 'label' => 'Alerts settings'],
                    ['key' => 'appearance', 'label' => 'Appearance'],
                ],
            ],
            [
                'group' => '08 · Subscription',
                'screens' => [
                    ['key' => 'subscription', 'label' => 'Pro'],
                    ['key' => 'plans', 'label' => 'Before purchase'],
                    ['key' => 'cancel', 'label' => 'Cancel'],
                    ['key' => 'cancelled', 'label' => 'Cancelled'],
                ],
            ],
            [
                'group' => '09 · Privacy & states',
                'screens' => [
                    ['key' => 'data', 'label' => 'Your data'],
                    ['key' => 'delete', 'label' => 'Delete account'],
                    ['key' => 'deleted', 'label' => 'Account deleted'],
                    ['key' => 'permissions', 'label' => 'Permissions'],
                    ['key' => 'signin', 'label' => 'Signed out'],
                ],
            ],
        ];
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $matches
     * @param  Collection<int, array<string, mixed>>  $clubs
     * @param  array<string, mixed>  $close
     * @return array<string, mixed>
     */
    private function appHome(Collection $matches, Collection $clubs, array $close): array
    {
        $featured = $matches->first();
        $coming = $matches->slice(1, 4)->values();
        $nearby = $matches->slice(1, 3)->values();
        $activityClubs = $clubs
            ->sortByDesc('upcoming_count')
            ->take(4)
            ->map(fn (array $club): array => [
                'name' => $club['name'],
                'slug' => $club['slug'],
                'text' => (int) $club['upcoming_count'] > 0
                    ? sprintf('%s added a new match', $club['name'])
                    : sprintf('%s updated the club profile', $club['name']),
                'when' => 'today',
            ])
            ->values()
            ->all();

        return [
            'greeting' => 'Your shooting',
            'place' => $close['place'] ?? $close['home_place'] ?? 'South Africa',
            'featured' => $featured,
            'coming' => $coming,
            'nearby' => $nearby,
            'activity' => $activityClubs,
            'initials' => 'P',
        ];
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $sports
     * @param  Collection<int, array<string, mixed>>  $clubs
     * @return array<string, mixed>
     */
    private function appOnboarding(Request $request, Collection $sports, Collection $clubs): array
    {
        $selectedSports = collect($request->query('choose', []))
            ->filter(fn (mixed $value): bool => is_string($value))
            ->values();
        $selectedClubs = collect($request->query('follow', []))
            ->filter(fn (mixed $value): bool => is_string($value))
            ->values();
        $province = Province::tryFrom($request->string('home')->toString());

        $families = [
            'rifle' => 'Rifle',
            'handgun' => 'Handgun',
            'shotgun' => 'Shotgun',
            'airgun' => 'Airgun',
            'multi' => 'Multi-gun',
        ];
        $sportsByFamily = collect($families)
            ->map(fn (string $label, string $key) => [
                'key' => $key,
                'label' => $label,
                'items' => $sports->filter(fn (array $sport): bool => $sport['family'] === $key)->values(),
            ])
            ->filter(fn (array $group): bool => $group['items']->isNotEmpty())
            ->values();

        $suggestedClubs = $clubs
            ->when($selectedSports->isNotEmpty(), fn ($collection) => $collection->filter(
                fn (array $club): bool => array_intersect($club['discipline_slugs'] ?? [], $selectedSports->all()) !== []
            ))
            ->when($province instanceof Province, fn ($collection) => $collection->filter(
                fn (array $club): bool => ($club['province_value'] ?? null) === $province->value
            ))
            ->take(6)
            ->values();

        if ($suggestedClubs->isEmpty()) {
            $suggestedClubs = $clubs->take(6)->values();
        }

        return [
            'sports' => $selectedSports,
            'clubs' => $selectedClubs,
            'province' => $province,
            'sports_by_family' => $sportsByFamily,
            'suggested_clubs' => $suggestedClubs,
        ];
    }

    public function manage(Request $request): View
    {
        $club = $this->manageClub($request);
        $matches = $this->manageMatchesFor($club);
        $profile = $this->manageProfile($club);

        return view('mockups.manage.index', [
            'club' => $club,
            'clubs' => $this->catalog->clubs(),
            'matches' => $matches->take(5)->values(),
            'matchCount' => $matches->count(),
            'profile' => $profile,
            'activity' => $this->manageActivity($matches),
        ]);
    }

    public function manageMatches(Request $request): View
    {
        $club = $this->manageClub($request);
        $window = trim($request->string('window')->toString());
        $matches = $this->manageMatchesFor($club, $window);

        return view('mockups.manage.matches', [
            'club' => $club,
            'clubs' => $this->catalog->clubs(),
            'matches' => $matches->values(),
            'window' => $window,
        ]);
    }

    public function manageMatchesNew(Request $request): View
    {
        $club = $this->manageClub($request);

        return view('mockups.manage.new-match', [
            'club' => $club,
            'clubs' => $this->catalog->clubs(),
            'sports' => $this->catalog->sports(),
            'ranges' => $this->catalog->ranges(),
        ]);
    }

    public function manageProfilePage(Request $request): View
    {
        $club = $this->manageClub($request);

        return view('mockups.manage.profile', [
            'club' => $club,
            'clubs' => $this->catalog->clubs(),
            'profile' => $this->manageProfile($club),
        ]);
    }

    /**
     * @return array{sports: Collection<int, string>, clubs: Collection<int, string>, province: string}
     */
    private function readFollows(Request $request): array
    {
        return [
            'sports' => collect($request->query('sport', []))
                ->filter(fn (mixed $slug): bool => is_string($slug) && $slug !== '')
                ->values(),
            'clubs' => collect($request->query('club', []))
                ->filter(fn (mixed $slug): bool => is_string($slug) && $slug !== '')
                ->values(),
            'province' => trim($request->string('province')->toString()),
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function manageClub(Request $request): ?array
    {
        $slug = trim($request->string('club')->toString());
        $clubs = $this->catalog->clubs();

        if ($slug !== '') {
            $match = $clubs->firstWhere('slug', $slug);

            if ($match !== null) {
                return $match;
            }
        }

        $sample = $this->catalog->samples()['club'];

        return ($sample !== null ? $clubs->firstWhere('slug', $sample) : null) ?? $clubs->first();
    }

    /**
     * @param  array<string, mixed>|null  $club
     * @return Collection<int, array<string, mixed>>
     */
    private function manageMatchesFor(?array $club, string $window = ''): Collection
    {
        if ($club === null) {
            return collect();
        }

        $today = now()->timezone('Africa/Johannesburg')->toDateString();

        return $this->catalog->publicMatches()
            ->filter(fn (array $match): bool => $match['organiser_slug'] === $club['slug'])
            ->filter(function (array $match) use ($window, $today): bool {
                if ($window === 'past') {
                    return $match['date'] !== null && $match['date'] < $today;
                }

                if ($window === 'draft') {
                    return ($match['status_value'] ?? null) === 'draft';
                }

                if ($window === 'missing') {
                    return (int) ($match['completeness']['percent'] ?? 100) < 80;
                }

                return true;
            });
    }

    /**
     * @param  array<string, mixed>|null  $club
     * @return array{percent: int, checks: list<array{label: string, ok: bool}>}
     */
    private function manageProfile(?array $club): array
    {
        if ($club === null) {
            return ['percent' => 0, 'checks' => []];
        }

        $checks = [
            ['label' => 'Name', 'ok' => filled($club['name'] ?? null)],
            ['label' => 'Province', 'ok' => filled($club['province'] ?? null)],
            ['label' => 'Description', 'ok' => strlen((string) ($club['description'] ?? '')) > 40],
            ['label' => 'Sports listed', 'ok' => ($club['disciplines'] ?? []) !== []],
            ['label' => 'Home range', 'ok' => filled($club['range'] ?? null)],
            ['label' => 'Website', 'ok' => filled($club['website'] ?? null)],
            ['label' => 'Email', 'ok' => filled($club['email'] ?? null)],
            ['label' => 'Phone', 'ok' => filled($club['phone'] ?? null)],
            ['label' => 'Verification', 'ok' => (bool) ($club['verified'] ?? false)],
        ];

        $percent = (int) round(collect($checks)->filter(fn (array $check): bool => $check['ok'])->count() / count($checks) * 100);

        return ['percent' => $percent, 'checks' => $checks];
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $matches
     * @return list<array{when: string, text: string}>
     */
    private function manageActivity(Collection $matches): array
    {
        return $matches
            ->take(4)
            ->map(fn (array $match): array => [
                'when' => $match['date_label'],
                'text' => sprintf('%s scheduled at %s', $match['title'], $match['range'] ?: $match['province'] ?: 'a range'),
            ])
            ->values()
            ->all();
    }

    public function adminDashboard(): View
    {
        $quality = $this->catalog->quality();

        return view('mockups.admin.dashboard', [
            'stats' => $this->catalog->stats(),
            'users' => $this->catalog->reports()['users'],
            'businesses' => $this->catalog->businesses()->count(),
            'summary' => $quality['summary'],
            'attention' => collect($quality['groups'])->flatMap(fn (array $group) => $group['items'])->take(8),
            'activity' => $this->catalog->dashboardActivity(),
            'recent' => $this->catalog->publicMatches()->sortByDesc('created_at')->take(5),
        ]);
    }

    public function adminMatches(Request $request): View
    {
        $q = trim($request->string('q')->toString());
        $window = trim($request->string('window')->toString());
        $sport = trim($request->string('sport')->toString());
        $province = trim($request->string('province')->toString());
        $today = now()->timezone('Africa/Johannesburg')->toDateString();

        $matches = $this->catalog->adminMatches()->filter(function (array $match) use ($q, $window, $sport, $province, $today): bool {
            if ($sport !== '' && ! in_array($sport, $match['discipline_slugs'], true)) {
                return false;
            }

            if ($province !== '' && $match['province_value'] !== $province) {
                return false;
            }

            if ($window === 'upcoming' && ($match['date'] === null || $match['date'] < $today || $match['status_value'] === 'draft')) {
                return false;
            }

            if ($window === 'past' && ($match['date'] === null || $match['date'] >= $today)) {
                return false;
            }

            if ($window === 'draft' && $match['status_value'] !== 'draft') {
                return false;
            }

            if ($window === 'published' && $match['status_value'] === 'draft') {
                return false;
            }

            if ($window === 'missing' && ($match['completeness']['percent'] ?? 100) >= 80 && filled($match['range']) && filled($match['entry_url'])) {
                return false;
            }

            if ($q === '') {
                return true;
            }

            $haystack = mb_strtolower($match['title'].' '.$match['organiser'].' '.$match['range'].' '.$match['discipline']);

            return str_contains($haystack, mb_strtolower($q));
        })->values();

        return view('mockups.admin.matches', [
            'matches' => $matches,
            'sports' => $this->catalog->sports(),
            'provinces' => Province::cases(),
        ]);
    }

    public function adminMatchEdit(?string $slug = null): View
    {
        $match = $slug === null && request()->query('new') === '1'
            ? null
            : $this->catalog->matchDetail($slug);

        abort_if($slug !== null && $match === null, 404);

        return view('mockups.admin.match-edit', [
            'match' => $match,
            'sports' => $this->catalog->sports(),
            'ranges' => $this->catalog->ranges(),
            'clubs' => $this->catalog->clubs(),
            'asOrganiser' => request()->query('as') === 'organiser',
        ]);
    }

    public function adminClubs(Request $request): View
    {
        $filter = trim($request->string('filter')->toString());
        $clubs = $this->catalog->clubs()->filter(function (array $club) use ($filter): bool {
            return match ($filter) {
                'incomplete' => (! filled($club['email']) && ! filled($club['phone'])) || $club['disciplines'] === [],
                'stale' => (bool) $club['is_stale'],
                'unverified' => ($club['verified'] ?? false) === false,
                'quiet' => (int) $club['upcoming_count'] === 0,
                default => true,
            };
        })->values();

        return view('mockups.admin.clubs', [
            'clubs' => $clubs,
        ]);
    }

    public function adminRanges(Request $request): View
    {
        $filter = trim($request->string('filter')->toString());
        $ranges = $this->catalog->ranges()->filter(function (array $range) use ($filter): bool {
            return match ($filter) {
                'gps' => ! $range['has_gps'],
                'distance' => $range['max_distance_m'] === null,
                'club' => $range['clubs'] === [],
                default => true,
            };
        })->values();

        return view('mockups.admin.ranges', [
            'ranges' => $ranges,
        ]);
    }

    public function adminSports(): View
    {
        return view('mockups.admin.sports', [
            'sports' => $this->catalog->sports(),
        ]);
    }

    public function adminIndustry(): View
    {
        return view('mockups.admin.industry', [
            'businesses' => $this->catalog->businesses(),
        ]);
    }

    public function adminSubmissions(): View
    {
        return view('mockups.admin.submissions');
    }

    public function adminQuality(): View
    {
        return view('mockups.admin.quality', [
            'quality' => $this->catalog->quality(),
        ]);
    }

    public function adminDuplicates(): View
    {
        return view('mockups.admin.duplicates', [
            'duplicates' => $this->catalog->duplicates(),
        ]);
    }

    public function adminReports(): View
    {
        return view('mockups.admin.reports', [
            'reports' => $this->catalog->reports(),
        ]);
    }

    public function adminAdvertising(): View
    {
        return view('mockups.admin.advertising', [
            'placements' => $this->catalog->placements(),
        ]);
    }

    /**
     * @return array<string, mixed>|null
     */
    private function selectedSport(Request $request): ?array
    {
        $slug = trim($request->string('sport')->toString());

        if ($slug === '') {
            return null;
        }

        $sport = $this->catalog->sports()->firstWhere('slug', $slug);

        return is_array($sport) ? $sport : null;
    }

    /**
     * @param  array<string, mixed>|null  $sport
     * @return list<string>
     */
    private function sportSlugs(?array $sport): array
    {
        if ($sport === null) {
            return [];
        }

        return array_values(array_unique(array_filter([
            $sport['slug'] ?? null,
            ...array_column($sport['children'] ?? [], 'slug'),
        ])));
    }

    /**
     * @return array{on: bool, denied: bool, km: int, lat: ?float, lng: ?float, province: ?string, place: ?string, gps: bool, home: string, home_place: string, using_home: bool, anywhere: bool}
     */
    private function closeFilter(Request $request): array
    {
        $km = (int) $request->query('km', 100);

        if (! in_array($km, [50, 100, 150], true)) {
            $km = 100;
        }

        $home = Province::tryFrom($request->string('home')->toString()) ?? Province::Gauteng;
        $requested = $request->string('province')->toString();
        $anywhere = $requested === 'all';
        $browse = Province::tryFrom($requested);
        $province = $anywhere ? null : ($browse ?? $home);
        $usingHome = ! $anywhere && ($browse === null || $browse === $home);
        $gpsLat = is_numeric($request->query('lat')) ? (float) $request->query('lat') : null;
        $gpsLng = is_numeric($request->query('lng')) ? (float) $request->query('lng') : null;
        $gps = $request->query('near') === '1' && $gpsLat !== null && $gpsLng !== null;
        $lat = $gpsLat;
        $lng = $gpsLng;

        if (! $gps && $province instanceof Province) {
            [$lat, $lng] = Geo::provinceCentroid($province);
        }

        $radiusOn = $request->has('km') && ($gps || $province instanceof Province);

        return [
            'on' => $radiusOn && $lat !== null && $lng !== null,
            'denied' => $request->query('near') === 'denied',
            'km' => $km,
            'lat' => $lat,
            'lng' => $lng,
            'province' => $province?->value,
            'place' => $province?->getLabel(),
            'gps' => $gps,
            'home' => $home->value,
            'home_place' => $home->getLabel(),
            'using_home' => $usingHome,
            'anywhere' => $anywhere,
        ];
    }

    /**
     * @param  array{on: bool, denied: bool, km: int, lat: ?float, lng: ?float, province: ?string, place: ?string, gps: bool, home: string, home_place: string, using_home: bool, anywhere: bool}  $close
     * @return array{grid: array<string, mixed>, day: string, day_label: string, events: list<array<string, mixed>>}
     */
    private function appCalendar(Request $request, array $close): array
    {
        $month = $request->string('month')->toString();

        if (! preg_match('/^\d{4}-\d{2}$/', $month)) {
            $month = now()->timezone('Africa/Johannesburg')->format('Y-m');
        }

        $filters = [];

        if ($close['province'] !== null) {
            $filters['province'] = $close['province'];
        }

        if ($close['on'] && $close['lat'] !== null && $close['lng'] !== null) {
            $filters['lat'] = $close['lat'];
            $filters['lng'] = $close['lng'];
            $filters['radius'] = $close['km'];
            $filters['sort'] = 'closest';
        }

        $grid = $this->catalog->calendar($month, $filters);
        $day = $request->string('day')->toString();

        if (! preg_match('/^\d{4}-\d{2}-\d{2}$/', $day) || ! str_starts_with($day, $month)) {
            $today = now()->timezone('Africa/Johannesburg')->toDateString();
            $day = str_starts_with($today, $month) ? $today : ($grid['agenda'][0]['date'] ?? $month.'-01');
        }

        $events = collect($grid['weeks'])->flatten(1)->firstWhere('date', $day)['events'] ?? [];

        return [
            'grid' => $grid,
            'day' => $day,
            'day_label' => Carbon::parse($day, 'Africa/Johannesburg')->format('l j M'),
            'events' => $events,
        ];
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $matches
     * @param  array{on: bool, denied: bool, km: int, lat: ?float, lng: ?float, province: ?string, place: ?string, gps: bool, home: string, home_place: string, using_home: bool, anywhere: bool}  $close
     * @return Collection<int, array<string, mixed>>
     */
    private function withinReach(Collection $matches, array $close): Collection
    {
        if ($close['province'] === null && $close['lat'] === null) {
            return $matches;
        }

        $filters = [];

        if ($close['gps']) {
            $filters['lat'] = $close['lat'];
            $filters['lng'] = $close['lng'];
            $filters['radius'] = $close['km'];
            $filters['sort'] = 'closest';
        } else {
            if ($close['province'] !== null) {
                $filters['province'] = $close['province'];
            }

            if ($close['lat'] !== null && $close['lng'] !== null) {
                $filters['lat'] = $close['lat'];
                $filters['lng'] = $close['lng'];

                if ($close['on']) {
                    $filters['radius'] = $close['km'];
                    $filters['sort'] = 'closest';
                }
            }
        }

        return $this->catalog->filterMatches($matches, $filters)['matches'];
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $rows
     * @param  array{on: bool, denied: bool, km: int, lat: ?float, lng: ?float, province: ?string, place: ?string, gps: bool, home: string, home_place: string, using_home: bool, anywhere: bool}  $close
     * @return Collection<int, array<string, mixed>>
     */
    private function withinPlace(Collection $rows, array $close): Collection
    {
        return $rows->filter(function (array $row) use ($close): bool {
            if ($close['province'] !== null && ($row['province_value'] ?? null) !== $close['province']) {
                return false;
            }

            if (! $close['on'] || $close['lat'] === null || $close['lng'] === null) {
                return true;
            }

            if (! is_numeric($row['lat'] ?? null) || ! is_numeric($row['lng'] ?? null)) {
                return true;
            }

            return Geo::haversineKm((float) $row['lat'], (float) $row['lng'], $close['lat'], $close['lng']) <= $close['km'];
        })->map(function (array $row) use ($close): array {
            if ($close['lat'] === null || $close['lng'] === null || ! is_numeric($row['lat'] ?? null) || ! is_numeric($row['lng'] ?? null)) {
                return $row;
            }

            $row['distance_km'] = Geo::haversineKm((float) $row['lat'], (float) $row['lng'], $close['lat'], $close['lng']);

            return $row;
        })->values();
    }

    /**
     * @param  array{q: string, scope: string, dropped: list<string>, follows: list<array{kind: string, key: string, slug: string, label: string, on: bool}>, results: Collection<int, array<string, mixed>>}  $search
     * @param  array{on: bool, denied: bool, km: int, lat: ?float, lng: ?float, province: ?string, place: ?string, gps: bool, home: string, home_place: string, using_home: bool, anywhere: bool}  $close
     * @return array{q: string, scope: string, dropped: list<string>, follows: list<array{kind: string, key: string, slug: string, label: string, on: bool}>, results: Collection<int, array<string, mixed>>}
     */
    private function withCloseResults(array $search, array $close): array
    {
        $search['results'] = $this->withinReach($search['results'], $close)->take(20)->values();

        return $search;
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $upcoming
     * @param  Collection<int, array<string, mixed>>  $sports
     * @param  Collection<int, array<string, mixed>>  $clubs
     * @return array{q: string, scope: string, dropped: list<string>, follows: list<array{kind: string, key: string, slug: string, label: string, on: bool}>, results: Collection<int, array<string, mixed>>}
     */
    private function eventSearch(Request $request, Collection $upcoming, Collection $sports, Collection $clubs): array
    {
        $q = Str::limit(trim(strip_tags($request->string('q')->toString())), 80, '');
        $dropped = collect($request->query('drop', []))
            ->filter(fn (mixed $value): bool => is_string($value))
            ->values()
            ->all();
        $follows = [];

        foreach ($upcoming->pluck('discipline_slug')->filter()->countBy()->sortDesc()->keys()->take(2) as $slug) {
            $sport = $sports->firstWhere('slug', $slug);

            if ($sport === null) {
                continue;
            }

            $key = 'sport:'.$slug;
            $follows[] = [
                'kind' => 'sport',
                'key' => $key,
                'slug' => $slug,
                'label' => $sport['name'],
                'on' => ! in_array($key, $dropped, true),
            ];
        }

        $clubSlug = $upcoming->pluck('organiser_slug')->filter()->countBy()->sortDesc()->keys()->first();
        $club = is_string($clubSlug) ? $clubs->firstWhere('slug', $clubSlug) : null;

        if ($club !== null) {
            $key = 'club:'.$club['slug'];
            $follows[] = [
                'kind' => 'club',
                'key' => $key,
                'slug' => $club['slug'],
                'label' => $club['name'],
                'on' => ! in_array($key, $dropped, true),
            ];

            if (filled($club['province_value'])) {
                $key = 'province:'.$club['province_value'];
                $follows[] = [
                    'kind' => 'province',
                    'key' => $key,
                    'slug' => $club['province_value'],
                    'label' => $club['province'],
                    'on' => ! in_array($key, $dropped, true),
                ];
            }
        }

        $active = collect($follows)->where('on', true);
        $scope = $request->query('scope') === 'all' ? 'all' : 'follows';
        $usingFollows = $scope === 'follows' && $active->isNotEmpty();
        $needle = mb_strtolower($q);
        $sportSlugs = $active->where('kind', 'sport')->pluck('slug');
        $clubSlugs = $active->where('kind', 'club')->pluck('slug');
        $provinceFollow = $active->firstWhere('kind', 'province');
        $province = is_array($provinceFollow) ? $provinceFollow['slug'] : null;

        $results = $upcoming->filter(function (array $match) use ($needle, $usingFollows, $sportSlugs, $clubSlugs, $province): bool {
            if ($needle !== '') {
                $haystack = mb_strtolower(implode(' ', array_filter([
                    $match['title'],
                    $match['discipline'],
                    $match['organiser'],
                    $match['range'],
                    $match['town'],
                    $match['province'],
                ])));

                if (! str_contains($haystack, $needle)) {
                    return false;
                }
            }

            if (! $usingFollows) {
                return true;
            }

            $sportHit = $sportSlugs->intersect($match['discipline_slugs'])->isNotEmpty();
            $clubHit = $clubSlugs->contains($match['organiser_slug']);
            $provinceHit = $province !== null && $match['province_value'] === $province;

            return $sportHit || $clubHit || $provinceHit;
        })->values();

        return [
            'q' => $q,
            'scope' => $scope,
            'dropped' => $dropped,
            'follows' => $follows,
            'results' => $results,
        ];
    }

    /**
     * @param  array<string, mixed>|null  $sport
     * @param  Collection<int, array<string, mixed>>  $upcoming
     * @return array{name: string, slug: string, items: list<array{key: string, label: string}>, matches: Collection<int, array<string, mixed>>}|null
     */
    private function packingKit(?array $sport, Collection $upcoming): ?array
    {
        if ($sport === null) {
            return null;
        }

        return [
            'name' => $sport['name'],
            'slug' => $sport['slug'],
            'items' => PackingKits::basics($sport['slug'], $sport['family']),
            'matches' => $upcoming
                ->filter(fn (array $match): bool => in_array($sport['slug'], $match['discipline_slugs'], true))
                ->take(4)
                ->values(),
        ];
    }

    /**
     * @param  array<string, mixed>|null  $match
     * @return array{match: array<string, mixed>|null, sport: string|null, rows: list<array{key: string, label: string, custom: bool, on: bool}>, added: list<string>, on: list<string>, has_sport: bool}
     */
    private function packingSession(Request $request, ?array $match): array
    {
        if ($match === null) {
            return [
                'match' => null,
                'sport' => null,
                'rows' => [],
                'added' => [],
                'on' => [],
                'has_sport' => false,
            ];
        }

        $basics = PackingKits::basics($match['discipline_slug'] ?? null, $match['family'] ?? null);
        $added = collect($request->query('added', []))
            ->filter(fn (mixed $value): bool => is_string($value))
            ->map(fn (string $value): string => Str::limit(trim(strip_tags($value)), 80, ''))
            ->filter()
            ->take(20);
        $item = Str::limit(trim(strip_tags($request->string('item')->toString())), 80, '');

        if ($item !== '') {
            $added->push($item);
        }

        $added = $added->unique()->values();
        $rows = [];

        foreach ($basics as $basic) {
            $rows[] = [
                'key' => $basic['key'],
                'label' => $basic['label'],
                'custom' => false,
                'on' => false,
            ];
        }

        foreach ($added as $index => $label) {
            $rows[] = [
                'key' => 'extra-'.$index,
                'label' => $label,
                'custom' => true,
                'on' => false,
            ];
        }

        $valid = array_column($rows, 'key');
        $on = collect($request->query('on', []))
            ->filter(fn (mixed $value): bool => is_string($value) && in_array($value, $valid, true))
            ->values()
            ->all();

        foreach ($rows as $index => $row) {
            $rows[$index]['on'] = in_array($row['key'], $on, true);
        }

        return [
            'match' => $match,
            'sport' => $match['discipline'],
            'rows' => $rows,
            'added' => $added->all(),
            'on' => $on,
            'has_sport' => $basics !== [],
        ];
    }
}
