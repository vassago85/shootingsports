<x-mockups.layout title="{{ data_get($range, 'name', 'Range') }}" active="ranges" :sample="data_get($range, 'name')">
    <div class="wrap" style="padding-bottom:48px">
        @if (! $range)
            <div class="mk-empty"><strong>No ranges in the register yet.</strong></div>
        @else
            <header class="mk-pagehead">
                <h1>{{ $range['name'] }}</h1>
                @if ($range['place'])
                    <p class="mk-lede">{{ $range['place'] }}</p>
                @endif
                <div class="mk-facts">
                    @if ($range['max_distance'])<span>{{ $range['max_distance'] }} maximum distance</span>@endif
                    @if ($range['access'])<span>{{ $range['access'] }}</span>@endif
                    @if ($range['day_fee'])<span>Day fee {{ $range['day_fee'] }}</span>@endif
                    @if ($range['bays'])<span>{{ $range['bays'] }} bays</span>@endif
                </div>
                @if ($range['directions'])
                    <div class="mk-actions">
                        <a class="btn ghost" href="{{ $range['directions'] }}">Directions</a>
                    </div>
                @endif
            </header>

            @if ($range['upcoming'] !== [])
                <section class="mk-section">
                    <h2>Upcoming at {{ $range['name'] }}</h2>
                    @foreach ($range['upcoming'] as $match)
                        <x-mockups.match-row :match="$match" />
                    @endforeach
                </section>
            @endif

            @if ($range['notes'])
                <section class="mk-section">
                    <h2>About</h2>
                    <div class="mk-prose"><p>{{ $range['notes'] }}</p></div>
                </section>
            @endif

            @if ($range['facilities'] !== [])
                <section class="mk-section">
                    <h2>Facilities</h2>
                    <div class="mk-meta">
                        @foreach ($range['facilities'] as $facility)
                            <span>{{ $facility }}</span>
                        @endforeach
                    </div>
                </section>
            @endif

            @if ($range['clubs'] !== [])
                <section class="mk-section">
                    <h2>Clubs using this range</h2>
                    @foreach ($range['clubs'] as $name)
                        <p>{{ $name }}</p>
                    @endforeach
                </section>
            @endif

            @if ($range['disciplines'] !== [])
                <section class="mk-section">
                    <h2>Sports</h2>
                    <p>{{ implode(' · ', $range['disciplines']) }}</p>
                </section>
            @endif

            @if ($range['has_gps'] || $range['address'])
                <section class="mk-section">
                    <h2>Location</h2>
                    @if ($range['address'])
                        <p>{{ $range['address'] }}</p>
                    @endif
                    @if ($range['has_gps'])
                        <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">
                        <div id="one-range" class="mk-map-canvas"></div>
                        <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
                        <script>
                            const map = L.map('one-range').setView([{{ $range['lat'] }}, {{ $range['lng'] }}], 12);
                            const cartoKey = @json($cartoApiKey);
                            const cartoStyle = @json(request()->query('theme') === 'dark' ? 'dark_all' : 'light_all');
                            if (cartoKey) {
                                L.tileLayer('https://{s}.basemaps.cartocdn.com/' + cartoStyle + '/{z}/{x}/{y}{r}.png?key=' + encodeURIComponent(cartoKey), { attribution: '&copy; OpenStreetMap &copy; CARTO', subdomains: 'abcd', maxZoom: 19 }).addTo(map);
                            } else {
                                L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', { attribution: '&copy; OpenStreetMap', maxZoom: 19 }).addTo(map);
                            }
                            L.circleMarker([{{ $range['lat'] }}, {{ $range['lng'] }}], { radius: 8, color: '#4e6b32', fillColor: '#1b1e19', fillOpacity: 1, weight: 2 }).addTo(map);
                        </script>
                    @endif
                </section>
            @endif
        @endif
    </div>
</x-mockups.layout>
