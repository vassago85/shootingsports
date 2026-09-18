<?php

namespace App\Http\Controllers;

use App\Enums\Province;
use App\Models\Discipline;
use App\Models\Event;
use App\Models\Organisation;
use App\Queries\PublicEventQuery;
use App\Services\Discovery\DiscoveryStats;
use App\Support\JsonLd;
use App\Support\Seo;
use Illuminate\Http\RedirectResponse;
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

    public function landing(string $province, Discipline $discipline): View
    {
        return $this->show($discipline, $province);
    }

    public function redirectLegacyProvince(Discipline $discipline, string $province): RedirectResponse
    {
        abort_unless(Province::fromUrlSlug($province) !== null, 404);

        return redirect()->route('clubs.landing', [$province, $discipline->slug], 301);
    }

    /**
     * @return array<string, mixed>
     */
    private function pageData(Discipline $discipline, ?Province $province): array
    {
        $discipline->load(['children', 'federation', 'parent']);

        $events = (new PublicEventQuery(
            disciplineSlug: $discipline->slug,
            provinceSlug: $province?->urlSlug(),
        ))->get();

        // Derived-first: a club that hosts published PRS matches but
        // never had `precision-rifle` attached to its pivot row still
        // belongs on this page. The service unions the pivot with
        // real event history.
        $clubs = $this->stats->clubsForDiscipline($discipline, $province);

        $ranges = $this->stats->rangesCountForDiscipline($discipline, $province);

        $listingCount = $clubs->count() + $events->count() + $ranges;
        $noindex = $province !== null && $listingCount < (int) config('seo.landing_min');

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
        $seoDescription = $this->composeDescription($discipline, $province, $events->count(), $clubs->count());

        $crumbs = [
            ['name' => 'Home', 'url' => route('home')],
            ['name' => 'Discover', 'url' => route('disciplines.index')],
            ['name' => $discipline->name, 'url' => route('disciplines.show', $discipline->slug)],
        ];

        if ($province !== null) {
            $crumbs[] = [
                'name' => $province->getLabel(),
                'url' => route('clubs.landing', [$province->urlSlug(), $discipline->slug]),
            ];
        }

        $members = $clubs->map(fn (Organisation $club): array => [
            'name' => $club->name,
            'url' => route('clubs.show', $club->slug),
            'id' => $club->schemaId(),
        ])->concat($events->map(fn (Event $event): array => [
            'name' => $event->title,
            'url' => $event->publicUrl(),
            'id' => $event->schemaId(),
        ]))->values()->all();

        $seo = $province !== null
            ? Seo::landing($discipline, $province, $seoDescription, $noindex)
            : Seo::discipline($discipline, $seoDescription);

        return [
            'discipline' => $discipline,
            'province' => $province,
            'events' => $events,
            'clubs' => $clubs,
            'familySiblings' => $familySiblings,
            'related' => $related,
            'noindex' => $noindex,
            'figures' => [
                'matches' => $events->count(),
                'clubs' => $clubs->count(),
                'distance' => $discipline->typical_distances,
                'provinces' => $provincesActive,
            ],
            'seo' => $seo,
            'seoDescription' => $seo->description,
            'jsonLd' => array_values(array_filter([
                $members !== [] ? JsonLd::itemList($members, $seo->title) : null,
                JsonLd::breadcrumbs($crumbs),
            ])),
        ];
    }

    private function composeDescription(Discipline $discipline, ?Province $province, int $matchCount, int $clubCount): string
    {
        $where = $province ? ' in '.$province->getLabel() : ' across South Africa';

        $matches = match ($matchCount) {
            0 => 'no matches on the calendar right now',
            1 => '1 upcoming match',
            default => "{$matchCount} upcoming matches",
        };

        $clubs = match ($clubCount) {
            0 => '',
            1 => ' at 1 listed club',
            default => " at {$clubCount} listed clubs",
        };

        $blurb = $discipline->short_blurb
            ? ' '.rtrim($discipline->short_blurb, '.').'.'
            : '';

        return $discipline->name.$where.' — '.$matches.$clubs.'.'.$blurb;
    }
}
