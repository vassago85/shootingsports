<x-mockups.v2.layout :title="data_get($range, 'name', 'Range')" active="ranges">
    <div class="v2-wrap v2-page">
        @if (! $range)
            <p class="v2-empty"><strong>No ranges in the register yet.</strong></p>
        @else
            <header style="padding-top:28px">
                <h1>{{ $range['name'] }}</h1>
                @if (filled($range['place']) && $range['place'] !== '—')<p class="v2-lede">{{ $range['place'] }}</p>@endif
                <div class="v2-facts">
                    @if ($range['max_distance'])<span>{{ $range['max_distance'] }} maximum distance</span>@endif
                    @if ($range['access'])<span>{{ $range['access'] }}</span>@endif
                    @if ($range['day_fee'])<span>Day fee {{ $range['day_fee'] }}</span>@endif
                    @if ($range['bays'])<span>{{ $range['bays'] }} bays</span>@endif
                </div>
                @if ($range['directions'])<div class="v2-actions"><a class="v2-btn v2-btn-line" href="{{ $range['directions'] }}">Directions</a></div>@endif
            </header>
            @if ($range['upcoming'] !== [])
                <section class="v2-section">
                    <h2>Upcoming at {{ $range['name'] }}</h2>
                    <div class="v2-list" style="margin-top:12px">
                        @foreach ($range['upcoming'] as $match)<x-mockups.v2.match-row :match="$match" />@endforeach
                    </div>
                </section>
            @endif
            @if ($range['notes'])<section class="v2-section"><h2>About</h2><div class="v2-prose"><p>{{ $range['notes'] }}</p></div></section>@endif
            @if ($range['facilities'] !== [])
                <section class="v2-section"><h2>Facilities</h2><div class="v2-tags">@foreach ($range['facilities'] as $facility)<span class="v2-tag">{{ $facility }}</span>@endforeach</div></section>
            @endif
            @if ($range['clubs'] !== [])
                <section class="v2-section"><h2>Clubs using this range</h2>@foreach ($range['clubs'] as $name)<p>{{ $name }}</p>@endforeach</section>
            @endif
            @if ($range['disciplines'] !== [])
                <section class="v2-section"><h2>Sports</h2><div class="v2-tags">@foreach ($range['disciplines'] as $name)<span class="v2-tag">{{ $name }}</span>@endforeach</div></section>
            @endif
            @if ($range['has_gps'] || $range['address'])
                <section class="v2-section">
                    <h2>Location</h2>
                    @if ($range['address'])<p>{{ $range['address'] }}</p>@endif
                    @if ($range['has_gps'])
                        <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">
                        <div id="one-range" class="v2-map" style="position:static;height:360px;margin-top:12px"></div>
                        <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
                        <script>
                            const map = L.map('one-range').setView([{{ $range['lat'] }}, {{ $range['lng'] }}], 12);
                            v2BaseMap(map);
                            L.circleMarker([{{ $range['lat'] }}, {{ $range['lng'] }}], { radius: 8, color: '#34754d', fillColor: '#10251f', fillOpacity: 1, weight: 2 }).addTo(map);
                        </script>
                    @endif
                </section>
            @endif
        @endif
    </div>
</x-mockups.v2.layout>
