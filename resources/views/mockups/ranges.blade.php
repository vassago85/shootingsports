<x-mockups.layout title="Find a shooting range" active="ranges">
    <div class="wrap" style="padding-bottom:40px">
        <header class="mk-pagehead">
            <p class="label">Ranges</p>
            <h1>Find a shooting range</h1>
            <p class="mk-lede">Ranges that host matches on the register.</p>
        </header>
        <div class="mk-views">
            <a href="{{ $mk('mockups.ranges', request()->except(['view', 'page'])) }}" @class(['on' => $view === 'list'])>List</a>
            <a href="{{ $mk('mockups.ranges', array_merge(request()->except(['view', 'page']), ['view' => 'map'])) }}" @class(['on' => $view === 'map'])>Map</a>
        </div>
        <form class="mk-filters" method="get">
            @if (request('device') === 'mobile')
                <input type="hidden" name="device" value="mobile">
            @endif
            @if ($view === 'map')
                <input type="hidden" name="view" value="map">
            @endif
            <label class="field">
                <span>Province</span>
                <select name="province">
                    <option value="">Any province</option>
                    @foreach ($provinces as $province)
                        <option value="{{ $province->value }}" @selected(request('province') === $province->value)>{{ $province->getLabel() }}</option>
                    @endforeach
                </select>
            </label>
            <label class="field">
                <span>Maximum distance</span>
                <select name="min_distance">
                    <option value="">Any</option>
                    @foreach ([100, 300, 600, 1000] as $metres)
                        <option value="{{ $metres }}" @selected((string) request('min_distance') === (string) $metres)>{{ $metres }} m or more</option>
                    @endforeach
                </select>
            </label>
            <label class="field">
                <span>Discipline</span>
                <select name="sport">
                    <option value="">Any sport</option>
                    @foreach ($sports as $sport)
                        <option value="{{ $sport['slug'] }}" @selected(request('sport') === $sport['slug'])>{{ $sport['name'] }}</option>
                    @endforeach
                </select>
            </label>
            <label class="mk-check"><input type="checkbox" name="visitors" value="1" @checked(request('visitors') === '1')> Visitors allowed</label>
            <button class="btn" type="submit">Filter</button>
        </form>
        @if ($hiddenForDistance > 0)
            <aside class="mk-internal">
                <span>Reviewer note</span>
                <p>{{ $hiddenForDistance }} ranges have no maximum distance, so they are omitted from this distance filter. The public list does not say why.</p>
            </aside>
        @endif
        @php
            $pins = $ranges->filter(fn (array $range): bool => $range['has_gps'])->values();
        @endphp
        @if ($view === 'map')
            <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">
            <div id="range-map" class="mk-map-canvas" style="margin-top:12px"></div>
            <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
            <script>
                const ranges = @json($pins);
                const map = L.map('range-map');
                const cartoKey = @json($cartoApiKey);
                if (cartoKey) {
                    L.tileLayer('https://{s}.basemaps.cartocdn.com/dark_all/{z}/{x}/{y}{r}.png?key=' + encodeURIComponent(cartoKey), { attribution: '&copy; OpenStreetMap &copy; CARTO', subdomains: 'abcd', maxZoom: 19 }).addTo(map);
                } else {
                    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', { attribution: '&copy; OpenStreetMap', maxZoom: 19 }).addTo(map);
                }
                const layer = L.featureGroup();
                ranges.forEach((range) => {
                    L.circleMarker([range.lat, range.lng], { radius: 6, color: '#6B7D3A', fillColor: '#0B0D0E', fillOpacity: 1, weight: 2 })
                        .bindPopup(range.name)
                        .addTo(layer);
                });
                if (ranges.length) {
                    layer.addTo(map);
                    map.fitBounds(layer.getBounds().pad(0.2));
                } else {
                    map.setView([-29, 25], 5);
                }
            </script>
        @endif
        @forelse ($ranges as $range)
            @php
                $place = collect([$range['town'] ?? null, $range['province'] ?? null])->filter(fn (?string $part): bool => filled($part) && $part !== '—')->implode(' · ');
                $facts = collect([$range['max_distance'], $range['access']])->filter()->implode(' · ');
            @endphp
            <a class="mk-dir" href="{{ $mk('mockups.range', ['slug' => $range['slug']]) }}">
                <span class="mk-dir-name">{{ $range['name'] }}</span>
                @if ($place !== '')
                    <span class="mk-dir-place">{{ $place }}</span>
                @endif
                @if ($facts !== '')
                    <span class="mk-dir-meta">{{ $facts }}</span>
                @endif
                @if ($range['disciplines'] !== [])
                    <span class="mk-dir-meta">{{ implode(' · ', array_slice($range['disciplines'], 0, 4)) }}</span>
                @endif
                @if ($range['upcoming_count'])
                    <span class="mk-dir-count">{{ $range['upcoming_count'] }} upcoming {{ \Illuminate\Support\Str::plural('match', $range['upcoming_count']) }}</span>
                @endif
            </a>
        @empty
            <p class="mk-empty"><strong>No ranges match these filters.</strong></p>
        @endforelse
    </div>
</x-mockups.layout>
