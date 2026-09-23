<x-layouts.public
    title="Match map"
    :description="$seoDescription"
    :canonical="route('map')"
    :json-ld="$jsonLd"
>
    <main id="main">
        <div class="wrap dir-page">
            <x-dir-hero page="matches" kicker="Matches" title="Map">
                {{ $totalMatches }} upcoming {{ $totalMatches === 1 ? 'match' : 'matches' }}.
                Select a pin to see what is shot there.
            </x-dir-hero>
        </div>

        <section class="match-board">
            <div class="wrap">
                <div class="mb-toolbar">
                    <div class="mb-toolbar-in">
                        <h2 class="mb-toolbar-title">
                            Matches
                            <small>
                                {{ $totalMatches }} {{ $totalMatches === 1 ? 'match' : 'matches' }}
                                · {{ count($pins) }} {{ count($pins) === 1 ? 'range' : 'ranges' }}
                            </small>
                        </h2>
                        <div class="view-toggle" role="group" aria-label="Matches view">
                            <a href="{{ route('calendar') }}" aria-pressed="false">List</a>
                            <a href="{{ route('calendar.month') }}" aria-pressed="false">Month</a>
                            <a href="{{ route('map') }}" aria-pressed="true">Map</a>
                        </div>
                    </div>
                </div>

                <link rel="stylesheet"
                      href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css"
                      crossorigin="">
                <link rel="stylesheet"
                      href="https://unpkg.com/leaflet.markercluster@1.5.3/dist/MarkerCluster.css"
                      crossorigin="">

                <div class="filters map-province-filters mb-toolbar-chips" role="group" aria-label="Zoom map to a province">
                    <button type="button" class="mb-chip is-default" data-province="" aria-pressed="{{ $selectedProvince ? 'false' : 'true' }}">All provinces</button>
                    @foreach ($provinces as $province)
                        <button
                            type="button"
                            class="mb-chip"
                            data-province="{{ $province->urlSlug() }}"
                            aria-pressed="{{ $selectedProvince === $province->urlSlug() ? 'true' : 'false' }}"
                            title="{{ $province->getLabel() }}"
                        >{{ $province->getLabel() }}</button>
                    @endforeach
                </div>

                <div
                    id="ss-map"
                    class="ss-map {{ $cartoApiKey ? '' : 'is-osm-fallback' }}"
                    data-pins="{{ json_encode($pins, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) }}"
                    data-centroids="{{ json_encode($centroids, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) }}"
                    data-province="{{ $selectedProvince ?? '' }}"
                    @if ($cartoApiKey) data-carto-key="{{ $cartoApiKey }}" @endif
                    role="region"
                    aria-label="Map of shooting ranges with upcoming matches"
                ></div>

                <ul class="map-legend" id="ss-map-legend">
                    @forelse ($pins as $pin)
                        <li class="{{ $pin['count'] === 0 ? 'is-quiet' : '' }}" data-province="{{ $pin['province_slug'] ?? '' }}">
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
                        <p class="label">Upcoming matches. Pin still needed</p>
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
                        crossorigin=""></script>
                <script src="https://unpkg.com/leaflet.markercluster@1.5.3/dist/leaflet.markercluster.js"
                        crossorigin=""></script>
                <script>
                    (function () {
                        var mount = function () {
                            if (typeof L === 'undefined' || typeof L.markerClusterGroup !== 'function') return;
                            var el = document.getElementById('ss-map');
                            if (! el || el.dataset.mapReady === '1') return;
                            el.dataset.mapReady = '1';
                            var pins = JSON.parse(el.dataset.pins || '[]');
                            var centroids = JSON.parse(el.dataset.centroids || '{}');

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

                            var cluster = L.markerClusterGroup({
                                showCoverageOnHover: false,
                                maxClusterRadius: 56,
                                disableClusteringAtZoom: 10,
                                spiderfyOnMaxZoom: true,
                                iconCreateFunction: function (c) {
                                    var children = c.getAllChildMarkers();
                                    var ranges = children.length;
                                    var matches = 0;
                                    for (var i = 0; i < children.length; i++) {
                                        matches += children[i].options.matchCount || 0;
                                    }
                                    var size = matches > 0 ? 'is-hot' : 'is-quiet';
                                    var label = matches > 0
                                        ? (matches + (matches === 1 ? ' match' : ' matches'))
                                        : (ranges + (ranges === 1 ? ' range' : ' ranges'));
                                    return L.divIcon({
                                        html: '<span class="ss-cluster-inner"><b>' + ranges + '</b><em>' + label + '</em></span>',
                                        className: 'ss-cluster ' + size,
                                        iconSize: L.point(52, 52),
                                    });
                                },
                            });

                            var markersByProvince = {};
                            pins.forEach(function (p) {
                                var colour = p.count > 0 ? '#6B7D3A' : '#5a6360';
                                var marker = L.circleMarker([p.lat, p.lng], {
                                    radius: p.radius,
                                    color: p.count > 0 ? '#4a5828' : '#5a6360',
                                    weight: 2,
                                    fillColor: colour,
                                    fillOpacity: p.count > 0 ? 0.65 : 0.35,
                                    matchCount: p.count || 0,
                                    provinceSlug: p.province_slug || '',
                                });
                                var tip = p.label;
                                if (p.count > 0) {
                                    tip += '. ' + p.count + (p.count === 1 ? ' match' : ' matches');
                                } else if (p.town) {
                                    tip += '. ' + p.town;
                                }
                                marker.bindTooltip(tip, { direction: 'top' });
                                marker.on('click', function () {
                                    window.location.href = p.url;
                                });
                                cluster.addLayer(marker);
                                var key = p.province_slug || '';
                                if (! markersByProvince[key]) markersByProvince[key] = [];
                                markersByProvince[key].push(marker);
                            });

                            map.addLayer(cluster);

                            var focusProvince = function (slug) {
                                var buttons = document.querySelectorAll('.map-province-filters [data-province]');
                                buttons.forEach(function (btn) {
                                    btn.setAttribute('aria-pressed', btn.getAttribute('data-province') === slug ? 'true' : 'false');
                                });

                                var legend = document.getElementById('ss-map-legend');
                                if (legend) {
                                    legend.querySelectorAll('li[data-province]').forEach(function (li) {
                                        var match = ! slug || li.getAttribute('data-province') === slug;
                                        li.hidden = ! match;
                                    });
                                }

                                var url = new URL(window.location.href);
                                if (slug) {
                                    url.searchParams.set('province', slug);
                                } else {
                                    url.searchParams.delete('province');
                                }
                                window.history.replaceState({}, '', url.pathname + url.search);

                                if (! slug) {
                                    var all = [];
                                    pins.forEach(function (p) { all.push([p.lat, p.lng]); });
                                    if (all.length > 1) {
                                        map.fitBounds(all, { padding: [36, 36], maxZoom: 9 });
                                    } else if (all.length === 1) {
                                        map.setView(all[0], 9);
                                    } else {
                                        map.setView([-28.8, 25.0], 5);
                                    }
                                    return;
                                }

                                var group = markersByProvince[slug] || [];
                                if (group.length > 0) {
                                    var bounds = L.featureGroup(group).getBounds();
                                    map.fitBounds(bounds, { padding: [40, 40], maxZoom: 10 });
                                    return;
                                }

                                var centre = centroids[slug];
                                if (centre) {
                                    map.setView([centre.lat, centre.lng], 8);
                                }
                            };

                            document.querySelectorAll('.map-province-filters [data-province]').forEach(function (btn) {
                                btn.addEventListener('click', function () {
                                    focusProvince(btn.getAttribute('data-province') || '');
                                });
                            });

                            var initial = el.dataset.province || '';
                            focusProvince(initial);
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
