<x-layouts.public
    title="Match map"
    :description="$seoDescription"
    :canonical="route('map')"
    :json-ld="$jsonLd"
>
    <main id="main">
        <section class="page-hero">
            <div class="wrap">
                <p class="label">The register on the map</p>
                <h1>What's on, where</h1>
                <p>
                    {{ $totalMatches }} upcoming {{ $totalMatches === 1 ? 'match' : 'matches' }}
                    across the country. Click a province to see the calendar filtered to that region.
                </p>
                {{-- View toggle: two anchors, one aria-pressed. Kept
                     as plain links (not JS) so it works with no-script
                     and the browser back button behaves as expected. --}}
                <div class="view-toggle" role="group" aria-label="Calendar view">
                    <a href="{{ route('calendar') }}" aria-pressed="false">List</a>
                    <a href="{{ route('map') }}" aria-pressed="true">Map</a>
                </div>
            </div>
        </section>

        <section class="block">
            <div class="wrap">
                {{-- Leaflet lives entirely on this page. Loaded from
                     unpkg so we don't pay the bundle cost on any
                     other route. If we ever pin a real SRI hash it
                     goes here; leaving it off for now so a Leaflet
                     patch release doesn't silently break the map. --}}
                <link rel="stylesheet"
                      href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css"
                      crossorigin="">

                <div
                    id="ss-map"
                    class="ss-map"
                    data-markers="{{ json_encode($markers, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) }}"
                    role="region"
                    aria-label="Map of upcoming matches by province"
                ></div>

                {{-- Text fallback + SEO surface: the same data as
                     the map, rendered as a list so screen readers,
                     no-JS visitors and Google all see it. --}}
                <ul class="map-legend">
                    @foreach ($markers as $marker)
                        <li class="{{ $marker['count'] === 0 ? 'is-quiet' : '' }}">
                            @if ($marker['count'] === 0)
                                <a href="{{ $marker['claim_url'] }}">
                                    <span class="prov">{{ $marker['label'] }}</span>
                                    <span class="ct">No matches listed in {{ $marker['short'] }} — know a club here?</span>
                                </a>
                            @else
                                <a href="{{ $marker['calendar_url'] }}">
                                    <span class="prov">{{ $marker['label'] }}</span>
                                    <span class="ct">{{ $marker['count'] }} {{ $marker['count'] === 1 ? 'match' : 'matches' }}</span>
                                </a>
                            @endif
                        </li>
                    @endforeach
                </ul>

                <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"
                        crossorigin=""
                        defer></script>
                <script>
                    /*
                     * UX audit cool-factor: province-clustered map.
                     *
                     * Reads the marker payload from the container's
                     * data attribute, drops a size-scaled circle per
                     * province, and links each bubble to the calendar
                     * filtered to that province. No clustering
                     * library needed — nine bubbles never overlap.
                     *
                     * Bail silently if Leaflet failed to load (bad
                     * network, ad-blocker) so the legend below stays
                     * the source of truth.
                     */
                    (function () {
                        var mount = function () {
                            if (typeof L === 'undefined') {
                                return;
                            }
                            var el = document.getElementById('ss-map');
                            if (! el) return;
                            var markers = JSON.parse(el.dataset.markers || '[]');
                            if (! markers.length) return;

                            var map = L.map(el, {
                                zoomControl: true,
                                scrollWheelZoom: false,
                                attributionControl: true,
                            }).setView([-28.8, 25.0], 5);

                            // Free OSM tiles + CSS mute (see .ss-map .leaflet-tile-pane).
                            // CARTO Positron now watermarks "API KEY REQUIRED" without
                            // a paid key — we do not ship third-party map credentials.
                            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                                maxZoom: 12,
                                minZoom: 4,
                                attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>',
                            }).addTo(map);

                            markers.forEach(function (m) {
                                if (m.count === 0) {
                                    L.circleMarker([m.lat, m.lng], {
                                        radius: 6,
                                        color: '#5a6360',
                                        weight: 1,
                                        fillColor: '#5a6360',
                                        fillOpacity: 0.35,
                                    }).bindTooltip(
                                        'No matches listed in ' + m.short + ' — know a club here?',
                                        { direction: 'top' }
                                    ).on('click', function () {
                                        window.location.href = m.claim_url;
                                    }).addTo(map);
                                    return;
                                }
                                var marker = L.circleMarker([m.lat, m.lng], {
                                    radius: m.radius,
                                    color: '#8a6516',
                                    weight: 2,
                                    fillColor: '#b3892b',
                                    fillOpacity: 0.55,
                                });
                                marker.bindTooltip(
                                    m.label + ' — ' + m.count + ' ' +
                                    (m.count === 1 ? 'match' : 'matches'),
                                    { direction: 'top', permanent: false }
                                );
                                marker.on('click', function () {
                                    window.location.href = m.calendar_url;
                                });
                                marker.addTo(map);
                            });
                        };

                        if (document.readyState === 'loading') {
                            document.addEventListener('DOMContentLoaded', mount);
                        } else {
                            mount();
                        }
                        // Leaflet is `defer`red — hook the load event
                        // too in case it lands after DOMContentLoaded.
                        window.addEventListener('load', mount);
                    })();
                </script>
            </div>
        </section>
    </main>
</x-layouts.public>
