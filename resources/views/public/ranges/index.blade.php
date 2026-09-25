<x-layouts.public :title="$seoTitle" :description="$seoDescription" :json-ld="$jsonLd">
    <main id="main" class="dir-page">
        <div class="wrap">
            <x-dir-hero page="ranges" kicker="Ranges" title="Find a shooting range">
                Ranges that host matches on the register.
            </x-dir-hero>

            @php
                $query = array_filter([
                    'province' => $province?->urlSlug(),
                    'division' => $division?->value,
                    'discipline' => $discipline?->slug,
                    'min_distance' => $minDistance,
                    'visitors' => $visitors ? '1' : null,
                ], fn ($value) => filled($value));
            @endphp

            <nav class="dir-tabs" aria-label="Range views">
                <a href="{{ route('ranges.index', $query) }}" @class(['on' => $view === 'list'])>List</a>
                <a href="{{ route('ranges.index', [...$query, 'view' => 'map']) }}" @class(['on' => $view === 'map'])>Map</a>
            </nav>

            <form class="dir-filters" method="get" action="{{ route('ranges.index') }}">
                @if ($division)
                    <input type="hidden" name="division" value="{{ $division->value }}">
                @endif
                @if ($view === 'map')
                    <input type="hidden" name="view" value="map">
                @endif
                <label class="dir-field">
                    <span>Province</span>
                    <select name="province">
                        <option value="">Any province</option>
                        @foreach ($provinces as $item)
                            <option value="{{ $item->urlSlug() }}" @selected($province === $item)>{{ $item->getLabel() }}</option>
                        @endforeach
                    </select>
                </label>
                <label class="dir-field">
                    <span>Maximum distance</span>
                    <select name="min_distance">
                        <option value="">Any</option>
                        @foreach ([100, 300, 600, 1000] as $metres)
                            <option value="{{ $metres }}" @selected($minDistance === $metres)>{{ $metres }} m or more</option>
                        @endforeach
                    </select>
                </label>
                <label class="dir-field">
                    <span>Discipline</span>
                    <select name="discipline">
                        <option value="">Any sport</option>
                        @foreach ($sports as $sport)
                            <option value="{{ $sport->slug }}" @selected($discipline?->is($sport))>{{ $sport->name }}</option>
                        @endforeach
                    </select>
                </label>
                <label class="dir-check">
                    <input type="checkbox" name="visitors" value="1" @checked($visitors)> Visitors allowed
                </label>
                <button class="btn dir-filter" type="submit">Filter</button>
            </form>

            <p class="dir-count"><strong>{{ $venues->count() }} {{ \Illuminate\Support\Str::plural('range', $venues->count()) }}</strong></p>

            <div @class(['dir-split', 'is-map' => $view === 'map'])>
                <div class="dir-list">
                    @forelse ($venues as $index => $venue)
                        @php
                            $initials = collect(preg_split('/\s+/', $venue->name))
                                ->filter()
                                ->take(2)
                                ->map(fn (string $word): string => mb_strtoupper(mb_substr($word, 0, 1)))
                                ->implode('');
                        @endphp
                        <a class="dir-row" href="{{ route('ranges.show', $venue->slug) }}">
                            @if ($cover = $venue->imageUrls()[0] ?? null)
                                <img class="dir-logo is-cover" src="{{ $cover }}" alt="">
                            @else
                                <span class="dir-initials">{{ $initials }}</span>
                            @endif
                            <span class="dir-main">
                                <strong>{{ $venue->name }} <x-listing-tier-badge :listing="$venue" /></strong>
                                @php $rangePlace = collect([$venue->town, $venue->province?->getLabel()])->filter()->implode(' · '); @endphp
                                @if ($rangePlace !== '')
                                    <span>{{ $rangePlace }}</span>
                                @endif
                            </span>
                            <span class="dir-side">
                                @if ($venue->max_distance_m)
                                    <span>{{ number_format($venue->max_distance_m) }} m</span>
                                @endif
                                @if ($venue->access)
                                    <span>{{ $venue->access->getLabel() }}</span>
                                @endif
                                @if ($venue->upcoming_matches_count > 0)
                                    <span>{{ $venue->upcoming_matches_count }} upcoming {{ \Illuminate\Support\Str::plural('match', $venue->upcoming_matches_count) }}</span>
                                @endif
                            </span>
                            <span class="dir-chev" aria-hidden="true">›</span>
                        </a>
                        @if ($index === 2)
                            <x-ad-slot page="ranges" placement-slot="in_feed_native" :limit="2" class="ss-partner--tight" hide-when-vacant />
                        @endif
                    @empty
                        <p class="empty">No ranges match these filters.</p>
                    @endforelse
                </div>
                <div id="range-map" class="dir-map"></div>
            </div>
        </div>
    </main>

    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" crossorigin="">
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    <script>
        const rangePins = @json($pins);
        const map = L.map('range-map', { scrollWheelZoom: false });
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            attribution: '&copy; OpenStreetMap'
        }).addTo(map);
        const layer = L.featureGroup();
        rangePins.forEach((pin) => {
            L.circleMarker([pin.lat, pin.lng], {
                radius: 6, color: '#34754d', fillColor: '#10251f', fillOpacity: 1, weight: 2
            }).addTo(layer);
        });
        if (rangePins.length) {
            layer.addTo(map);
            map.fitBounds(layer.getBounds().pad(0.2));
        } else {
            map.setView([-29, 25], 5);
        }
    </script>
</x-layouts.public>
