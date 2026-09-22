<x-mockups.layout title="Match map" description="Upcoming matches on a map." active="matches">
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">
    <div class="wrap">
        <header class="mk-pagehead">
            <p class="label">Matches</p>
            <h1>Map</h1>
            <p class="mk-lede">{{ $total }} upcoming {{ $total === 1 ? 'match' : 'matches' }}. Select a pin to see what is shot there.</p>
        </header>
        <x-mockups.match-views />
        <x-mockups.match-filters :sports="$sports" :provinces="$provinces" />
        @include('mockups.partials.ad-space', ['sponsors' => $sponsors, 'limit' => $sponsors->count()])
    </div>
    @if ($unlocated !== [])
        <div class="wrap">
            <aside class="mk-internal">
                <span>Reviewer note</span>
                <p>{{ count($unlocated) }} {{ count($unlocated) === 1 ? 'match has' : 'matches have' }} no GPS, so {{ count($unlocated) === 1 ? 'it is' : 'they are' }} listed under the map and not pinned. Public copy does not mention missing coordinates.</p>
            </aside>
        </div>
    @endif
    <div class="mk-map">
        <div id="mk-map" class="mk-map-canvas"></div>
        <div class="mk-map-panel" id="mk-map-panel">
            @foreach ($markers as $marker)
                <article class="mk-map-item" id="loc-{{ $marker['id'] }}" data-lat="{{ $marker['lat'] }}" data-lng="{{ $marker['lng'] }}">
                    <h3>{{ $marker['name'] }}</h3>
                    <p class="mk-sub">{{ $marker['place'] }}</p>
                    @foreach ($marker['events'] as $event)
                        <p><a href="{{ $event['href'] }}">{{ $event['date_label'] }} — {{ $event['title'] }}</a></p>
                    @endforeach
                </article>
            @endforeach
            @if ($unlocated !== [])
                <h3 style="margin-top:18px;font-size:16px;text-transform:uppercase">Also coming up</h3>
                @foreach ($unlocated as $match)
                    <p><a href="{{ $mk('mockups.match', ['slug' => $match['slug']]) }}">{{ $match['date_label'] }} — {{ $match['title'] }}</a></p>
                    <p class="mk-sub">{{ collect([$match['town'], $match['province'], $match['range']])->filter()->implode(' · ') }}</p>
                @endforeach
            @endif
        </div>
    </div>
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
        <script>
            const markers = @json($markers);
            const map = L.map('mk-map');
            const cartoKey = @json($cartoApiKey);
            if (cartoKey) {
                L.tileLayer('https://{s}.basemaps.cartocdn.com/dark_all/{z}/{x}/{y}{r}.png?key=' + encodeURIComponent(cartoKey), {
                    attribution: '&copy; OpenStreetMap &copy; CARTO',
                    subdomains: 'abcd',
                    maxZoom: 19
                }).addTo(map);
            } else {
                L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                    attribution: '&copy; OpenStreetMap',
                    maxZoom: 19
                }).addTo(map);
            }
            const layer = L.featureGroup();
            markers.forEach((marker) => {
                const pin = L.circleMarker([marker.lat, marker.lng], {
                    radius: 7,
                    color: '#6B7D3A',
                    weight: 2,
                    fillColor: '#0B0D0E',
                    fillOpacity: 1
                }).addTo(layer);
                pin.on('click', () => {
                    document.querySelectorAll('.mk-map-item').forEach((el) => el.classList.remove('is-on'));
                    const item = document.getElementById('loc-' + marker.id);
                    if (item) {
                        item.classList.add('is-on');
                        item.scrollIntoView({ block: 'nearest' });
                    }
                });
            });
            if (markers.length) {
                layer.addTo(map);
                map.fitBounds(layer.getBounds().pad(0.2));
            } else {
                map.setView([-29, 25], 5);
            }
            document.querySelectorAll('.mk-map-item').forEach((item) => {
                item.addEventListener('click', () => {
                    const lat = parseFloat(item.dataset.lat);
                    const lng = parseFloat(item.dataset.lng);
                    if (!Number.isNaN(lat)) {
                        map.setView([lat, lng], 10);
                    }
                });
            });
        </script>
</x-mockups.layout>
