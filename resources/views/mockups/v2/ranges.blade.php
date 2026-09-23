<x-mockups.v2.layout title="Find a shooting range" active="ranges">
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">
    <div class="v2-wrap v2-page">
        <header class="v2-hero">
            <div class="v2-hero-copy">
                <p class="v2-kicker">Ranges</p>
                <h1>Find a shooting range</h1>
                <p class="v2-lede">Ranges that host matches on the register.</p>
            </div>
            <div class="v2-hero-art is-range" role="img" aria-label="Outdoor shooting range"></div>
        </header>
        <nav class="v2-tabs" aria-label="Range views">
            <a href="{{ $mk('mockups.v2.ranges', request()->except(['view', 'page'])) }}" @class(['on' => $view === 'list'])><x-mockups.v2.icon name="list" /> List</a>
            <a href="{{ $mk('mockups.v2.ranges', array_merge(request()->except(['view', 'page']), ['view' => 'map'])) }}" @class(['on' => $view === 'map'])><x-mockups.v2.icon name="map" /> Map</a>
        </nav>
        <form class="v2-filters cols-4" method="get">
            @include('mockups.v2.partials.keep')
            @if ($view === 'map')<input type="hidden" name="view" value="map">@endif
            <label class="v2-field">
                <span><x-mockups.v2.icon name="pin" /> Province</span>
                <select name="province">
                    <option value="">Any province</option>
                    @foreach ($provinces as $province)
                        <option value="{{ $province->value }}" @selected(request('province') === $province->value)>{{ $province->getLabel() }}</option>
                    @endforeach
                </select>
            </label>
            <label class="v2-field">
                <span><x-mockups.v2.icon name="ruler" /> Maximum distance</span>
                <select name="min_distance">
                    <option value="">Any</option>
                    @foreach ([100, 300, 600, 1000] as $metres)
                        <option value="{{ $metres }}" @selected((string) request('min_distance') === (string) $metres)>{{ $metres }} m or more</option>
                    @endforeach
                </select>
            </label>
            <label class="v2-field">
                <span><x-mockups.v2.icon name="target" /> Discipline</span>
                <select name="sport">
                    <option value="">Any sport</option>
                    @foreach ($sports as $sport)
                        <option value="{{ $sport['slug'] }}" @selected(request('sport') === $sport['slug'])>{{ $sport['name'] }}</option>
                    @endforeach
                </select>
            </label>
            <label class="v2-check"><input type="checkbox" name="visitors" value="1" @checked(request('visitors') === '1')> Visitors allowed</label>
            <button class="v2-btn v2-btn-green" type="submit">Filter</button>
        </form>
        @if ($hiddenForDistance > 0)
            <aside class="v2-note"><span>Reviewer note</span>{{ $hiddenForDistance }} ranges have no maximum distance, so they are omitted from this distance filter. The public list does not say why.</aside>
        @endif
        @php $pins = $ranges->filter(fn (array $range): bool => $range['has_gps'])->values(); @endphp
        <div class="v2-results-head"><strong>{{ $ranges->count() }} {{ \Illuminate\Support\Str::plural('range', $ranges->count()) }}</strong></div>
        <div @class(['v2-split' => $view === 'list'])>
            <div class="v2-list">
                @forelse ($ranges as $range)
                    <x-mockups.v2.range-row :range="$range" />
                @empty
                    <p class="v2-empty"><strong>No ranges match these filters.</strong></p>
                @endforelse
            </div>
            <div id="v2-range-map" class="v2-map" @if($view === 'map') style="grid-column:1/-1;order:-1" @endif></div>
        </div>
    </div>
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    <script>
        const ranges = @json($pins);
        const map = L.map('v2-range-map');
        const pins = {};
        v2BaseMap(map);
        const layer = L.featureGroup();
        const quiet = { radius: 6, color: '#34754d', fillColor: '#10251f', fillOpacity: 1, weight: 2 };
        const hot = { radius: 8, color: '#c5e07a', fillColor: '#34754d', fillOpacity: 1, weight: 2 };
        ranges.forEach((range) => {
            const marker = L.circleMarker([range.lat, range.lng], quiet).addTo(layer);
            pins[range.slug] = marker;
            marker.on('click', () => {
                Object.values(pins).forEach((pin) => pin.setStyle(quiet));
                marker.setStyle(hot);
                document.querySelectorAll('.v2-range').forEach((el) => el.classList.remove('is-on'));
                const row = document.getElementById('range-' + range.slug);
                if (row) { row.classList.add('is-on'); row.scrollIntoView({ block: 'nearest' }); }
            });
        });
        document.querySelectorAll('.v2-range').forEach((row) => {
            row.addEventListener('click', (event) => {
                if (event.target.closest('a')) return;
                const marker = pins[row.dataset.slug];
                if (!marker) return;
                Object.values(pins).forEach((pin) => pin.setStyle(quiet));
                marker.setStyle(hot);
                map.panTo(marker.getLatLng());
                document.querySelectorAll('.v2-range').forEach((el) => el.classList.remove('is-on'));
                row.classList.add('is-on');
            });
        });
        if (ranges.length) { layer.addTo(map); map.fitBounds(layer.getBounds().pad(0.2)); } else { map.setView([-29, 25], 5); }
    </script>
</x-mockups.v2.layout>
