<x-mockups.layout title="{{ data_get($range, 'name', 'Range') }}" active="ranges" :sample="data_get($range, 'name')">
    <div class="wrap" style="padding-bottom:48px">
        @if (! $range)
            <div class="mk-empty"><strong>No ranges in the register yet.</strong></div>
        @else
            <header class="mk-pagehead">
                <p class="label">Range</p>
                <h1>{{ $range['name'] }}</h1>
                <p class="mk-lede">{{ $range['place'] ?: 'Town not listed' }}</p>
                <div class="mk-actions">
                    <a class="btn" href="{{ $range['directions'] }}">Directions</a>
                </div>
            </header>
            <section class="mk-section">
                <h2>Range information</h2>
                <dl class="mk-dl">
                    <dt>Address</dt><dd>{{ $range['address'] ?: 'Not listed' }}</dd>
                    <dt>GPS</dt><dd>{{ $range['has_gps'] ? $range['lat'].', '.$range['lng'] : 'Not listed' }}</dd>
                    <dt>Maximum distance</dt><dd>{{ $range['max_distance'] ?: 'Not listed' }}</dd>
                    <dt>Disciplines</dt><dd>{{ $range['disciplines'] !== [] ? implode(', ', $range['disciplines']) : 'Not listed' }}</dd>
                    <dt>Access</dt><dd>{{ $range['access'] ?: 'Not listed' }}</dd>
                    <dt>Visitors</dt><dd>{{ $range['access'] ?: 'Not listed' }}</dd>
                    <dt>Operating days</dt><dd>Not recorded</dd>
                    <dt>Day fee</dt><dd>{{ $range['day_fee'] ?: 'Not listed' }}</dd>
                    <dt>Bays</dt><dd>{{ $range['bays'] ?: 'Not listed' }}</dd>
                    <dt>Contact</dt><dd>Not recorded on ranges</dd>
                </dl>
            </section>
            @if ($range['notes'])
                <section class="mk-section">
                    <h2>About</h2>
                    <div class="mk-prose"><p>{{ $range['notes'] }}</p></div>
                </section>
            @endif
            <section class="mk-section">
                <h2>Facilities</h2>
                @if ($range['facilities'] === [])
                    <p>No facilities are recorded for this range.</p>
                @else
                    <div class="mk-meta">
                        @foreach ($range['facilities'] as $facility)
                            <span>{{ $facility }}</span>
                        @endforeach
                    </div>
                @endif
            </section>
            <section class="mk-section">
                <h2>Upcoming matches at this range</h2>
                @forelse ($range['upcoming'] as $match)
                    <x-mockups.match-row :match="$match" />
                @empty
                    <p>No upcoming matches are listed here.</p>
                @endforelse
            </section>
            <section class="mk-section">
                <h2>Clubs using this range</h2>
                @forelse ($range['clubs'] as $name)
                    <p>{{ $name }}</p>
                @empty
                    <p>No club is linked through an upcoming match yet.</p>
                @endforelse
            </section>
            <section class="mk-section">
                <h2>Map and directions</h2>
                @if ($range['has_gps'])
                    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">
                    <div id="one-range" class="mk-map-canvas"></div>
                    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
                    <script>
                        const map = L.map('one-range').setView([{{ $range['lat'] }}, {{ $range['lng'] }}], 12);
                        const cartoKey = @json($cartoApiKey);
                        if (cartoKey) {
                            L.tileLayer('https://{s}.basemaps.cartocdn.com/dark_all/{z}/{x}/{y}{r}.png?key=' + encodeURIComponent(cartoKey), { attribution: '&copy; OpenStreetMap &copy; CARTO', subdomains: 'abcd', maxZoom: 19 }).addTo(map);
                        } else {
                            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', { attribution: '&copy; OpenStreetMap', maxZoom: 19 }).addTo(map);
                        }
                        L.circleMarker([{{ $range['lat'] }}, {{ $range['lng'] }}], { radius: 8, color: '#6B7D3A', fillColor: '#0B0D0E', fillOpacity: 1, weight: 2 }).addTo(map);
                    </script>
                @else
                    <p>A map pin is not available. Directions still search on the range name and town.</p>
                @endif
                <p style="margin-top:12px"><a class="btn ghost" href="{{ $range['directions'] }}">Open directions</a></p>
            </section>
        @endif
    </div>
</x-mockups.layout>
