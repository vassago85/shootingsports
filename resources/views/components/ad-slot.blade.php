@props([
    'page',
    'placementSlot',
    'limit' => 3,
])

@php
    $placements = \App\Support\AdPlacements::for($page, $placementSlot, (int) $limit);
@endphp

<aside {{ $attributes->class('ad-rail') }} aria-label="Advertising">
    @if ($placements->isNotEmpty())
        <p class="ad-label">Sponsored</p>
        <div class="ad-stack">
            @foreach ($placements as $placement)
                @php
                    $href = $placement->destinationUrl();
                    $img = $placement->imageUrl();
                @endphp
                <article class="ad-card">
                    @if ($img)
                        @if ($href)
                            <a href="{{ $href }}" rel="sponsored noopener noreferrer" class="ad-media">
                                <img src="{{ $img }}" alt="">
                            </a>
                        @else
                            <div class="ad-media"><img src="{{ $img }}" alt=""></div>
                        @endif
                    @endif
                    <div class="ad-copy">
                        @if ($placement->headline)
                            <h3>
                                @if ($href)
                                    <a href="{{ $href }}" rel="sponsored noopener noreferrer">{{ $placement->headline }}</a>
                                @else
                                    {{ $placement->headline }}
                                @endif
                            </h3>
                        @elseif ($placement->provider)
                            <h3>
                                <a href="{{ route('suppliers.show', $placement->provider->slug) }}">{{ $placement->provider->name }}</a>
                            </h3>
                        @endif
                        @if ($placement->body)
                            <p>{{ $placement->body }}</p>
                        @endif
                    </div>
                </article>
            @endforeach
        </div>
    @else
        <a class="ad-vacant" href="{{ route('advertise') }}">
            <p class="ad-label">Advertising</p>
            <p class="ad-vacant-kicker">This space is available</p>
            <h3>Advertise here</h3>
            <p>A quiet place on the register — seen by clubs, match directors and shooters looking for the next match.</p>
            <span class="ad-vacant-cta">Enquire about this space →</span>
        </a>
    @endif
</aside>
