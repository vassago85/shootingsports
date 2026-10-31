<?php

namespace App\Http\Controllers;

use App\Enums\Province;
use App\Models\Article;
use App\Models\Discipline;
use App\Models\Event;
use App\Queries\PublicEventQuery;
use App\Services\Discovery\DiscoveryStats;
use App\Support\DisciplinePages;
use App\Support\JsonLd;
use App\Support\Rankings;
use App\Support\Seo;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Str;
use Illuminate\View\View;

class DisciplineController extends Controller
{
    public function __construct(private readonly DiscoveryStats $stats) {}

    public function index(): View
    {
        $upcomingCounts = Discipline::upcomingCounts();
        /*
         * UX audit #11: sort by upcoming-match count (desc) first, then
         * name. The default sort by family/sort_order buried disciplines
         * with actual matches under 19 zero-count tiles — the second
         * section on the site was mostly dead ends. Zero-count tiles
         * still render (a follow button in the view keeps them useful)
         * but sink to the bottom.
         */
        $disciplines = Discipline::query()
            ->where('is_published', true)
            ->whereNull('parent_id')
            ->excludingVerificationFixtures()
            ->get()
            ->map(function (Discipline $discipline) use ($upcomingCounts): Discipline {
                $discipline->setAttribute('events_count', $upcomingCounts[$discipline->id] ?? 0);

                return $discipline;
            })
            ->sort(function (Discipline $a, Discipline $b): int {
                // Composite sort: upcoming count DESC, then name ASC.
                // Using <=> on arrays gives us a single-pass stable
                // comparator without three chained sortBy calls.
                return [$b->getAttribute('events_count'), $a->name]
                    <=> [$a->getAttribute('events_count'), $b->name];
            })
            ->values();

        // ItemList: the ordered discipline tiles as a schema.org list
        // so Google understands this is a curated collection page and
        // can render a rich results carousel. Zero-count tiles are
        // included because they still lead somewhere useful.
        $itemList = JsonLd::itemList(
            $disciplines->map(fn (Discipline $d): array => [
                'name' => $d->name,
                'url' => route('disciplines.show', $d->slug),
            ])->all(),
            'South African shooting disciplines',
        );

        $breadcrumbs = JsonLd::breadcrumbs([
            ['name' => 'Home', 'url' => route('home')],
            ['name' => 'Discover', 'url' => route('disciplines.index')],
        ]);

        return view('public.disciplines.index', [
            'disciplines' => $disciplines,
            'jsonLd' => [$itemList, $breadcrumbs],
        ]);
    }

    public function show(Discipline $discipline, ?string $province = null): View
    {
        abort_unless($discipline->is_published, 404);

        $provinceEnum = $province ? Province::fromUrlSlug($province) : null;

        abort_if($province !== null && $provinceEnum === null, 404);

        return view('public.disciplines.show', $this->pageData($discipline, $provinceEnum));
    }

    public function rankings(Discipline $discipline, Rankings $rankings): View
    {
        abort_unless($discipline->is_published, 404);

        return view('public.disciplines.rankings', [
            'discipline' => $discipline,
            'year' => (int) now()->year,
            'rows' => $rankings->forDiscipline($discipline),
        ]);
    }

    public function about(Discipline $discipline): View
    {
        abort_unless($discipline->is_published, 404);

        $data = $this->pageData($discipline, null);
        $data['seo'] = Seo::disciplineAbout($discipline);
        $data['jsonLd'] = [
            JsonLd::breadcrumbs([
                ['name' => 'Home', 'url' => route('home')],
                ['name' => 'Discover', 'url' => route('disciplines.index')],
                ['name' => $discipline->name, 'url' => route('disciplines.show', $discipline->slug)],
                ['name' => 'More information', 'url' => route('disciplines.about', $discipline->slug)],
            ]),
        ];

        return view('public.disciplines.about', $data);
    }

    public function landing(string $province, Discipline $discipline): RedirectResponse
    {
        abort_unless(Province::fromUrlSlug($province) !== null, 404);

        return redirect()->route('disciplines.province', [$discipline->slug, $province], 301);
    }

    /**
     * @return array<string, mixed>
     */
    private function pageData(Discipline $discipline, ?Province $province): array
    {
        $discipline->load(['children', 'federation', 'parent', 'videos', 'divisionLinks']);

        $events = (new PublicEventQuery(
            disciplineSlug: $discipline->slug,
            provinceSlug: $province?->urlSlug(),
        ))->get();

        // Derived-first: a club that hosts published PRS matches but
        // never had `precision-rifle` attached to its pivot row still
        // belongs on this page. The service unions the pivot with
        // real event history.
        $clubs = $this->stats->clubsForDiscipline($discipline, $province);

        // Ranges: keep both the count (drives SEO min-listing gate)
        // and the actual list so the show view can render venues that
        // host this discipline. Same union pivot ∪ event-history rule
        // as clubs.
        $ranges = $this->stats->rangesForDiscipline($discipline, $province);

        $noindex = $province !== null && ! DisciplinePages::hasPublicRecord($discipline, $province);

        $directoryClubs = $province === null ? $clubs : $this->stats->clubsForDiscipline($discipline);
        $directoryRanges = $province === null ? $ranges : $this->stats->rangesForDiscipline($discipline);
        $clubProvinces = $clubs
            ->pluck('province')
            ->filter()
            ->unique(fn (Province $item): string => $item->value)
            ->sortBy(fn (Province $item): string => $item->getLabel())
            ->values();

        $familySiblings = Discipline::query()
            ->where('is_published', true)
            ->where('family', $discipline->family)
            ->whereNull('parent_id')
            ->orderBy('sort_order')
            ->get();

        $related = Discipline::query()
            ->where('is_published', true)
            ->where('id', '!=', $discipline->id)
            ->whereNull('parent_id')
            ->where(function ($q) use ($discipline): void {
                $q->where('family', $discipline->family)
                    ->orWhere('id', '!=', $discipline->id);
            })
            ->where('id', '!=', $discipline->parent_id)
            ->orderByRaw('case when family = ? then 0 else 1 end', [$discipline->family->value])
            ->orderBy('sort_order')
            ->limit(4)
            ->get();

        // Active-provinces uses the same derivation as `clubs` — it
        // must count Gauteng, MP and WC when there are published PRS
        // matches in each, whether or not clubs remembered to attach
        // the discipline to themselves.
        $provincesActive = $this->stats->activeProvincesForDiscipline($discipline);

        // Filter-aware SEO description: "12 upcoming IPSC matches at
        // 4 clubs in Gauteng." Numbers change over time; keeping them
        // in the description (not the title) avoids CTR volatility
        // from Google re-rendering title tags.
        $seoDescription = $this->composeDescription($discipline, $province, $events->count(), $events->first());

        $crumbs = [
            ['name' => 'Home', 'url' => route('home')],
            ['name' => 'Discover', 'url' => route('disciplines.index')],
            ['name' => $discipline->name, 'url' => route('disciplines.show', $discipline->slug)],
        ];

        if ($province !== null) {
            $crumbs[] = [
                'name' => $province->getLabel(),
                'url' => route('disciplines.province', [$discipline->slug, $province->urlSlug()]),
            ];
        }

        $members = $events->map(fn (Event $event): array => [
            'name' => $event->title,
            'url' => $event->publicUrl(),
            'id' => $event->schemaId(),
        ])->values()->all();

        $seo = $province !== null
            ? Seo::landing($discipline, $province, $seoDescription, $noindex, $events->count())
            : Seo::discipline($discipline, $seoDescription, $events->count());

        return [
            'discipline' => $discipline,
            'province' => $province,
            'events' => $events,
            'clubs' => $clubs,
            'ranges' => $ranges,
            'familySiblings' => $familySiblings,
            'related' => $related,
            'sponsorDivisions' => $discipline->divisions()->take(3)->values(),
            'answer' => DisciplinePages::paragraph($discipline, $province, $events, $clubProvinces, $discipline->federation),
            'introHtml' => filled($discipline->intro) ? Str::markdown($discipline->intro) : null,
            'provinceDirectory' => DisciplinePages::directory($directoryClubs, $directoryRanges, $discipline),
            'recentResults' => DisciplinePages::recentResults($discipline, $province),
            'nationalUpcoming' => $province !== null && $events->isEmpty()
                ? DisciplinePages::nationalUpcoming($discipline)
                : collect(),
            'noindex' => $noindex,
            'figures' => [
                'matches' => $events->count(),
                'clubs' => $clubs->count(),
                'ranges' => $ranges->count(),
                'distance' => $discipline->typical_distances,
                'provinces' => $provincesActive,
            ],
            'seo' => $seo,
            'seoDescription' => $seo->description,
            'articles' => Article::query()
                ->published()
                ->whereHas('disciplines', fn ($query) => $query->whereKey($discipline->id))
                ->orderByDesc('published_at')
                ->limit(3)
                ->get(),
            'jsonLd' => array_values(array_filter([
                $members !== [] ? JsonLd::itemList($members, $seo->title) : null,
                ...$events->map(fn (Event $event): array => JsonLd::event($event))->all(),
                JsonLd::breadcrumbs($crumbs),
            ])),
        ];
    }

    private function composeDescription(Discipline $discipline, ?Province $province, int $matchCount, ?Event $next): string
    {
        $where = $province ? ' in '.$province->getLabel() : ' across South Africa';
        $when = $next?->starts_at?->timezone((string) config('app.timezone'))->format('j M Y');
        $blurb = $discipline->short_blurb
            ? ' '.rtrim((string) $discipline->short_blurb, '.').'.'
            : '';

        if ($matchCount === 0) {
            return 'No upcoming '.$discipline->name.' match is listed'.$where.' yet.'.$blurb;
        }

        $listed = $matchCount === 1
            ? 'There is 1 upcoming '.$discipline->name.' match'.$where.'.'
            : 'There are '.$matchCount.' upcoming '.$discipline->name.' matches'.$where.'.';

        $nextSentence = $next !== null && $when !== null
            ? ' The next is '.$next->title.' on '.$when.'.'
            : '';

        return $listed.$nextSentence.$blurb;
    }
}
