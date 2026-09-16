<?php

namespace App\Http\Controllers;

use App\Models\Event;
use App\Models\Venue;
use App\Support\JsonLd;
use App\Support\PublicCache;
use Illuminate\Support\Facades\Cache;
use Illuminate\View\View;

/**
 * Public map: real range pins for venues that have coordinates,
 * sized by upcoming match count. Venues without pins stay off the
 * map but appear in the unpinned list so staff know what to fix.
 */
class MapController extends Controller
{
    public function __invoke(): View
    {
        $payload = Cache::remember(
            PublicCache::key('map.venue-pins'),
            300,
            function (): array {
                $counts = Event::query()
                    ->upcoming()
                    ->whereNotNull('venue_id')
                    ->selectRaw('venue_id, count(*) as c')
                    ->groupBy('venue_id')
                    ->pluck('c', 'venue_id');

                $venues = Venue::query()
                    ->published()
                    ->whereNotNull('lat')
                    ->whereNotNull('lng')
                    ->orderBy('name')
                    ->get(['id', 'slug', 'name', 'town', 'province', 'lat', 'lng']);

                $max = max(1, (int) ($counts->max() ?: 1));
                $minR = 8;
                $maxR = 22;

                $pins = $venues
                    ->map(function (Venue $venue) use ($counts, $max, $minR, $maxR): ?array {
                        $count = (int) ($counts[$venue->id] ?? 0);
                        $radius = $count === 0
                            ? $minR
                            : (int) round($minR + ($maxR - $minR) * ($count / $max));

                        return [
                            'slug' => $venue->slug,
                            'label' => $venue->name,
                            'town' => $venue->town,
                            'province' => $venue->province?->code(),
                            'lat' => (float) $venue->lat,
                            'lng' => (float) $venue->lng,
                            'count' => $count,
                            'radius' => $radius,
                            'url' => route('ranges.show', $venue->slug),
                            'calendar_url' => route('calendar', ['venue' => $venue->slug]),
                        ];
                    })
                    ->filter()
                    ->values()
                    ->all();

                $unpinned = Venue::query()
                    ->published()
                    ->where(function ($q): void {
                        $q->whereNull('lat')->orWhereNull('lng');
                    })
                    ->whereIn('id', $counts->keys())
                    ->orderBy('name')
                    ->get(['slug', 'name', 'town'])
                    ->map(fn (Venue $v): array => [
                        'label' => $v->name,
                        'town' => $v->town,
                        'url' => route('ranges.show', $v->slug),
                        'count' => (int) ($counts[$v->id] ?? 0),
                    ])
                    ->all();

                return [
                    'pins' => $pins,
                    'unpinned' => $unpinned,
                    'totalMatches' => (int) $counts->sum(),
                ];
            }
        );

        $pinCount = count($payload['pins']);
        $seoDescription = $pinCount > 0
            ? $payload['totalMatches'].' upcoming matches across '.$pinCount.' pinned ranges on the South African map.'
            : 'Upcoming shooting matches across South Africa — range pins appear as venues get geocoded.';

        return view('public.map', [
            'pins' => $payload['pins'],
            'unpinned' => $payload['unpinned'],
            'totalMatches' => $payload['totalMatches'],
            'seoDescription' => $seoDescription,
            'cartoApiKey' => filled(config('services.carto.api_key'))
                ? (string) config('services.carto.api_key')
                : null,
            'jsonLd' => [
                JsonLd::breadcrumbs([
                    ['name' => 'Home', 'url' => route('home')],
                    ['name' => 'Map', 'url' => route('map')],
                ]),
            ],
        ]);
    }
}
