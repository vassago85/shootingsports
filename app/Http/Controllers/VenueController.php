<?php

namespace App\Http\Controllers;

use App\Enums\Province;
use App\Models\Venue;
use App\Queries\PublicEventQuery;
use App\Services\Discovery\DiscoveryStats;
use App\Services\Venues\VenueResolver;
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

        $venues = Venue::query()
            ->published()
            ->with('disciplines')
            ->when($province, fn ($q) => $q->where('province', $province))
            ->orderByTier()
            ->get();

        $countPhrase = match ($venues->count()) {
            0 => 'No ranges listed yet',
            1 => '1 range',
            default => $venues->count().' ranges',
        };

        $seoTitle = $province ? 'Shooting ranges in '.$province->getLabel() : 'Shooting ranges in South Africa';
        $seoDescription = $province
            ? 'Shooting ranges and venues in '.$province->getLabel().' — '.$countPhrase.' on the South African register.'
            : $countPhrase.' across South Africa — the venues where matches actually happen.';

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

        return view('public.ranges.index', [
            'venues' => $venues,
            'province' => $province,
            'provinces' => Province::cases(),
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
