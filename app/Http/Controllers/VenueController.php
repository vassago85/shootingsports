<?php

namespace App\Http\Controllers;

use App\Enums\Division;
use App\Enums\Province;
use App\Enums\VenueAccess;
use App\Models\Discipline;
use App\Models\Venue;
use App\Queries\PublicEventQuery;
use App\Services\Discovery\DiscoveryStats;
use App\Services\Venues\VenueResolver;
use App\Support\Geo;
use App\Support\JsonLd;
use App\Support\Seo;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class VenueController extends Controller
{
    public function __construct(
        private readonly DiscoveryStats $stats,
        private readonly VenueResolver $resolver,
    ) {}

    public function index(Request $request): View
    {
        $province = $request->string('province')->toString()
            ? Province::fromUrlSlug($request->string('province')->toString())
            : null;
        $division = Division::fromPublicQuery($request->string('division')->toString());
        $minDistance = in_array((int) $request->input('min_distance'), [100, 300, 600, 1000], true)
            ? (int) $request->input('min_distance')
            : null;
        $visitors = $request->boolean('visitors');
        $disciplineSlug = $request->string('discipline')->toString();
        $discipline = $disciplineSlug !== ''
            ? Discipline::query()->where('slug', $disciplineSlug)->where('is_published', true)->first()
            : null;
        $view = $request->string('view')->toString() === 'map' ? 'map' : 'list';

        $venues = Venue::query()
            ->published()
            ->with('disciplines')
            ->withCount(['events as upcoming_matches_count' => fn ($query) => $query->upcoming()])
            ->when($province, fn ($q) => $q->where('province', $province))
            ->when($division, fn ($q) => $q->inDivision($division))
            ->when($discipline, fn ($q) => $q->whereHas('disciplines', fn ($links) => $links->whereKey($discipline->id)))
            ->when($minDistance, fn ($q) => $q->where('max_distance_m', '>=', $minDistance))
            ->when($visitors, fn ($q) => $q->where('access', VenueAccess::Public))
            ->orderByTier()
            ->get();

        $countPhrase = match ($venues->count()) {
            0 => 'No ranges listed yet',
            1 => '1 range',
            default => $venues->count().' ranges',
        };

        $seoTitle = $province ? 'Shooting ranges in '.$province->getLabel() : 'Shooting ranges in South Africa';
        $seoDescription = $province
            ? 'Shooting ranges and venues in '.$province->getLabel().'. '.$countPhrase.' on the South African register.'
            : $countPhrase.' across South Africa. The venues where matches actually happen.';

        $itemList = JsonLd::itemList(
            $venues->map(fn (Venue $v): array => [
                'name' => $v->name,
                'url' => route('ranges.show', $v->slug),
                'id' => $v->schemaId(),
            ])->all(),
            $seoTitle,
        );

        $breadcrumbs = JsonLd::breadcrumbs([
            ['name' => 'Home', 'url' => route('home')],
            ['name' => 'Ranges', 'url' => route('ranges.index')],
        ]);

        $pins = $venues
            ->filter(fn (Venue $venue): bool => $venue->hasCoordinates()
                && Geo::isInsideSouthAfrica((float) $venue->lat, (float) $venue->lng))
            ->map(fn (Venue $venue): array => [
                'slug' => $venue->slug,
                'lat' => (float) $venue->lat,
                'lng' => (float) $venue->lng,
            ])
            ->values();

        return view('public.ranges.index', [
            'venues' => $venues,
            'pins' => $pins,
            'province' => $province,
            'division' => $division,
            'discipline' => $discipline,
            'minDistance' => $minDistance,
            'visitors' => $visitors,
            'view' => $view,
            'provinces' => Province::cases(),
            'sports' => Discipline::query()->where('is_published', true)->orderBy('name')->get(['id', 'name', 'slug']),
            'seoTitle' => $seoTitle,
            'seoDescription' => $seoDescription,
            'jsonLd' => [$itemList, $breadcrumbs],
        ]);
    }

    public function show(string $slug): View|RedirectResponse
    {
        $resolved = $this->resolver->findBySlug($slug);

        abort_if($resolved === null, 404);

        // Alias hit: send the client to the canonical URL with a 301
        // so search engines collapse the old slug and shared links do
        // not accumulate duplicate impressions.
        if (! $resolved['canonical']) {
            return redirect()->route('ranges.show', $resolved['venue']->slug, status: 301);
        }

        $venue = $resolved['venue'];

        $venue->load('disciplines');

        $events = (new PublicEventQuery(venueId: $venue->id))->get();

        return view('public.ranges.show', [
            'venue' => $venue,
            'events' => $events,
            'seo' => Seo::forVenue($venue),
            // Same derivation story as clubs: disciplines a range has
            // actually hosted, plus the clubs that have hosted here.
            'inferredDisciplines' => $this->stats->inferredDisciplinesForVenue($venue),
            'clubsUsingRange' => $this->stats->clubsUsingVenue($venue),
            'jsonLd' => [
                JsonLd::venue($venue),
                JsonLd::breadcrumbs([
                    ['name' => 'Home', 'url' => route('home')],
                    ['name' => 'Ranges', 'url' => route('ranges.index')],
                    ['name' => $venue->name, 'url' => route('ranges.show', $venue->slug)],
                ]),
            ],
        ]);
    }
}
