<?php

namespace App\Http\Controllers;

use App\Enums\Province;
use App\Models\Event;
use App\Support\Geo;
use App\Support\JsonLd;
use App\Support\PublicCache;
use Illuminate\Support\Facades\Cache;
use Illuminate\View\View;

/**
 * UX audit — cool factor: province-clustered map of upcoming matches.
 *
 * The public map surface. Aggregates every published upcoming match
 * by the province of its venue (with a fall-back to the host's
 * province when the venue does not name one) so we can drop 9
 * bubbles sized proportional to their match count on the SA map.
 *
 * A dedicated route (`/map`) rather than a `?view=map` toggle on
 * `/calendar` — this gives Google its own indexable page with its
 * own OG image and title, and lets us cross-link from the calendar
 * without polluting existing URL semantics. `/calendar?view=map`
 * still redirects here for parity with the audit's suggestion.
 */
class MapController extends Controller
{
    public function __invoke(): View
    {
        $provinceCounts = Cache::remember(
            PublicCache::key('map.province-counts'),
            300,
            function (): array {
                $rows = [];

                Event::query()
                    ->upcoming()
                    ->with(['venue:id,province', 'hostOrganisation:id,province'])
                    ->select(['id', 'venue_id', 'host_organisation_id'])
                    ->get()
                    ->each(function (Event $event) use (&$rows): void {
                        $province = $event->venue?->province
                            ?? $event->hostOrganisation?->province;

                        if (! $province instanceof Province) {
                            return;
                        }

                        $rows[$province->value] = ($rows[$province->value] ?? 0) + 1;
                    });

                return $rows;
            }
        );

        /*
         * Build the marker payload up-front so the view only has to
         * loop and print. We include every province — even zeroes —
         * so the map reads as a national register at a glance.
         * Sizes scale from 18px (zero) to 44px (top count) via a
         * simple linear rule capped at MAX_R; nine provinces mean
         * we never need clustering.
         *
         * `max($provinceCounts)` needs at least one element, so we
         * fall back to 1 when the aggregate is empty (fresh install,
         * no matches). Splat-into-max was silently correct on hot
         * data but blew up as a scalar `max(1)` on cold data.
         */
        $max = empty($provinceCounts) ? 1 : max(array_values($provinceCounts));
        $minR = 18;
        $maxR = 44;

        $markers = collect(Province::cases())
            ->map(function (Province $province) use ($provinceCounts, $max, $minR, $maxR): array {
                [$lat, $lng] = Geo::provinceCentroid($province);
                $count = $provinceCounts[$province->value] ?? 0;

                // Linear interpolation of radius by count fraction —
                // zero-count bubbles still render at the minimum radius
                // so an empty province is a visible "no matches here
                // yet" rather than a missing pin.
                $radius = $count === 0
                    ? $minR
                    : (int) round($minR + ($maxR - $minR) * ($count / $max));

                return [
                    'slug' => $province->urlSlug(),
                    'label' => $province->getLabel(),
                    'short' => $province->code(),
                    'lat' => $lat,
                    'lng' => $lng,
                    'count' => $count,
                    'radius' => $radius,
                    'calendar_url' => route('calendar', ['province' => $province->urlSlug()]),
                    'claim_url' => route('claim'),
                ];
            })
            ->values()
            ->all();

        $totalMatches = array_sum($provinceCounts);

        $seoDescription = $totalMatches > 0
            ? $totalMatches.' upcoming shooting matches across South Africa, plotted by province. Click any bubble to open that province\'s calendar.'
            : 'Upcoming shooting matches across South Africa, plotted by province — click a bubble to see the calendar for that province.';

        return view('public.map', [
            'markers' => $markers,
            'totalMatches' => $totalMatches,
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
