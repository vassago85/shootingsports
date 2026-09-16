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
                    on {{ count($pins) }} {{ count($pins) === 1 ? 'pinned range' : 'pinned ranges' }}.
                    Click a pin for the range page.
                </p>
                <div class="view-toggle" role="group" aria-label="Calendar view">
                    <a href="{{ route('calendar') }}" aria-pressed="false">List</a>
                    <a href="{{ route('calendar.month') }}" aria-pressed="false">Month</a>
                    <a href="{{ route('map') }}" aria-pressed="true">Map</a>
                </div>
            </div>
        </section>

        <section class="block">
            <div class="wrap">
                <link rel="stylesheet"
                      href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css"
                      crossorigin="">

                <div
                    id="ss-map"
                    class="ss-map {{ $cartoApiKey ? '' : 'is-osm-fallback' }}"
                    data-pins="{{ json_encode($pins, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) }}"
                    @if ($cartoApiKey) data-carto-key="{{ $cartoApiKey }}" @endif
                    role="region"
                    aria-label="Map of shooting ranges with upcoming matches"
                ></div>

                <ul class="map-legend">
                    @forelse ($pins as $pin)
                        <li class="{{ $pin['count'] === 0 ? 'is-quiet' : '' }}">
                            <a href="{{ $pin['url'] }}">
                                <span class="prov">{{ $pin['label'] }}</span>
                                <span class="ct">
                                    @if ($pin['count'] > 0)
                                        {{ $pin['count'] }} {{ $pin['count'] === 1 ? 'match' : 'matches' }}
                                    @else
                                        {{ $pin['town'] }}{{ $pin['province'] ? ' · '.$pin['province'] : '' }}
                                    @endif
                                </span>
                            </a>
                        </li>
                    @empty
                        <li class="is-quiet">
                            <span class="prov">No pinned ranges yet</span>
                            <span class="ct">Run venues:geocode after deploy</span>
                        </li>
                    @endforelse
                </ul>

                @if (count($unpinned) > 0)
                    <div class="map-unpinned" style="margin-top:28px">
                        <p class="label">Upcoming matches — pin still needed</p>
                        <ul class="map-legend">
                            @foreach ($unpinned as $row)
                                <li class="is-quiet">
                                    <a href="{{ $row['url'] }}">
                                        <span class="prov">{{ $row['label'] }}</span>
                                        <span class="ct">{{ $row['count'] }} {{ $row['count'] === 1 ? 'match' : 'matches' }} · {{ $row['town'] }}</span>
                                    </a>
                                </li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"
                        crossorigin=""
                        defer></script>
                <script>
                    (function () {
                        var mount = function () {
                            if (typeof L === 'undefined') return;
                            var el = document.getElementById('ss-map');
                            if (! el) return;
                            var pins = JSON.parse(el.dataset.pins || '[]');

                            var map = L.map(el, {
                                zoomControl: true,
                                scrollWheelZoom: false,
                                attributionControl: true,
                            }).setView([-28.8, 25.0], 5);

                            var cartoKey = el.dataset.cartoKey || '';
                            if (cartoKey) {
                                L.tileLayer('https://{s}.basemaps.cartocdn.com/light_all/{z}/{x}/{y}{r}.png?key=' + encodeURIComponent(cartoKey), {
                                    maxZoom: 14,
                                    minZoom: 4,
                                    subdomains: 'abcd',
                                    attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> &copy; <a href="https://carto.com/attributions">CARTO</a>',
                                }).addTo(map);
                            } else {
                                L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                                    maxZoom: 14,
                                    minZoom: 4,
                                    attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>',
                                }).addTo(map);
                            }

                            if (! pins.length) return;

                            var bounds = [];
                            pins.forEach(function (p) {
                                var colour = p.count > 0 ? '#b3892b' : '#5a6360';
                                var marker = L.circleMarker([p.lat, p.lng], {
                                    radius: p.radius,
                                    color: p.count > 0 ? '#8a6516' : '#5a6360',
                                    weight: 2,
                                    fillColor: colour,
                                    fillOpacity: p.count > 0 ? 0.65 : 0.35,
                                });
                                var tip = p.label;
                                if (p.count > 0) {
                                    tip += ' — ' + p.count + (p.count === 1 ? ' match' : ' matches');
                                } else if (p.town) {
                                    tip += ' — ' + p.town;
                                }
                                marker.bindTooltip(tip, { direction: 'top' });
                                marker.on('click', function () {
                                    window.location.href = p.url;
                                });
                                marker.addTo(map);
                                bounds.push([p.lat, p.lng]);
                            });

                            if (bounds.length > 1) {
                                map.fitBounds(bounds, { padding: [36, 36], maxZoom: 9 });
                            } else if (bounds.length === 1) {
                                map.setView(bounds[0], 9);
                            }
                        };

                        if (document.readyState === 'loading') {
                            document.addEventListener('DOMContentLoaded', mount);
                        } else {
                            mount();
                        }
                        window.addEventListener('load', mount);
                    })();
                </script>
            </div>
        </section>
    </main>
</x-layouts.public>
