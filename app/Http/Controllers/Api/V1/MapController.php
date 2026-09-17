<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\Province;
use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\Venue;
use App\Support\Geo;
use App\Support\PublicCache;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;

/**
 * Range-pin payload for the mobile map screen. Mirrors the shape
 * used by the web MapController's cache so a single query pool
 * warms both surfaces.
 */
class MapController extends Controller
{
    public function __invoke(): JsonResponse
    {
        $payload = Cache::remember(
            PublicCache::key('api.v1.map.venue-pins'),
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

                $pins = $venues
                    ->map(fn (Venue $venue): array => [
                        'slug' => $venue->slug,
                        'name' => $venue->name,
                        'town' => $venue->town,
                        'province' => $venue->province?->getLabel(),
                        'province_slug' => $venue->province?->urlSlug(),
                        'lat' => (float) $venue->lat,
                        'lng' => (float) $venue->lng,
                        'count' => (int) ($counts[$venue->id] ?? 0),
                    ])
                    ->values()
                    ->all();

                return [
                    'pins' => $pins,
                    'total_matches' => (int) $counts->sum(),
                ];
            }
        );

        $centroids = [];

        foreach (Province::cases() as $province) {
            [$lat, $lng] = Geo::provinceCentroid($province);
            $centroids[] = [
                'slug' => $province->urlSlug(),
                'label' => $province->getLabel(),
                'lat' => $lat,
                'lng' => $lng,
            ];
        }

        return response()->json([
            'pins' => $payload['pins'],
            'centroids' => $centroids,
            'total_matches' => $payload['total_matches'],
        ]);
    }
}
