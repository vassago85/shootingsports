<x-mockups.v2.layout title="Match map" active="matches">
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">
    <div class="v2-wrap v2-page">
        <header class="v2-hero">
            <div class="v2-hero-copy">
                <p class="v2-kicker">Matches</p>
                <h1>Map</h1>
                <p class="v2-lede">{{ $total }} upcoming {{ $total === 1 ? 'match' : 'matches' }}. Select a pin to see what is shot there.</p>
            </div>
            <div class="v2-hero-art is-match" role="img" aria-label="Clay target range"></div>
        </header>
        <nav class="v2-tabs" aria-label="Match views">
            <a href="{{ $mk('mockups.v2.matches', request()->except('page')) }}"><x-mockups.v2.icon name="list" /> List</a>
            <a href="{{ $mk('mockups.v2.matches.calendar', request()->except('page')) }}"><x-mockups.v2.icon name="calendar" /> Calendar</a>
            <a class="on" href="{{ $mk('mockups.v2.matches.map', request()->except('page')) }}"><x-mockups.v2.icon name="map" /> Map</a>
        </nav>
        <form class="v2-filters" method="get">
            @include('mockups.v2.partials.keep')
            <label class="v2-field"><span>Sport</span>
                <select name="sport"><option value="">Any sport</option>
                    @foreach ($sports as $sport)<option value="{{ $sport['slug'] }}" @selected(request('sport') === $sport['slug'])>{{ $sport['name'] }}</option>@endforeach
                </select>
            </label>
            <label class="v2-field"><span>Province</span>
                <select name="province"><option value="">Any province</option>
                    @foreach ($provinces as $province)<option value="{{ $province->value }}" @selected(request('province') === $province->value)>{{ $province->getLabel() }}</option>@endforeach
                </select>
            </label>
            <label class="v2-field"><span>From</span><input type="date" name="from" value="{{ request('from') }}"></label>
            <label class="v2-field"><span>To</span><input type="date" name="to" value="{{ request('to') }}"></label>
            <span></span>
            <button class="v2-btn v2-btn-green" type="submit">Find matches</button>
        </form>
        @include('mockups.v2.partials.ad-space', ['sponsors' => $sponsors, 'limit' => $sponsors->count()])
        <div class="v2-map-page">
            <div id="v2-match-map" class="v2-map"></div>
            <div class="v2-map-panel">
                @foreach ($markers as $marker)
                    <article class="v2-map-item" id="loc-{{ $marker['id'] }}">
                        <h3>{{ $marker['name'] }}</h3>
                        @if (filled($marker['place']))<p>{{ $marker['place'] }}</p>@endif
                        @foreach ($marker['events'] as $event)
                            <p><a href="{{ $mk('mockups.v2.match', ['slug' => $event['slug']]) }}">{{ $event['date_label'] }} — {{ $event['title'] }}</a></p>
                        @endforeach
                    </article>
                @endforeach
                @if ($unlocated !== [])
                    <article class="v2-map-item">
                        <h3>Also coming up</h3>
                        @foreach ($unlocated as $match)
                            <p><a href="{{ $mk('mockups.v2.match', ['slug' => $match['slug']]) }}">{{ $match['date_label'] }} — {{ $match['title'] }}</a></p>
                        @endforeach
                    </article>
                @endif
            </div>
        </div>
    </div>
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    <script>
            const markers = @json($markers);
            const map = L.map('v2-match-map');
            v2BaseMap(map);
        const layer = L.featureGroup();
        markers.forEach((marker) => {
            const pin = L.circleMarker([marker.lat, marker.lng], { radius: 7, color: '#34754d', weight: 2, fillColor: '#10251f', fillOpacity: 1 }).addTo(layer);
            pin.on('click', () => {
                document.querySelectorAll('.v2-map-item').forEach((el) => el.classList.remove('is-on'));
                const item = document.getElementById('loc-' + marker.id);
                if (item) { item.classList.add('is-on'); item.scrollIntoView({ block: 'nearest' }); }
            });
        });
        if (markers.length) { layer.addTo(map); map.fitBounds(layer.getBounds().pad(0.2)); } else { map.setView([-29, 25], 5); }
    </script>
</x-mockups.v2.layout>
