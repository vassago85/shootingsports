@props([
    'page',
    'placementSlot',
    'limit' => 3,
])

@php
    $placements = \App\Support\AdPlacements::for($page, $placementSlot, (int) $limit);
@endphp

@if ($placements->isNotEmpty())
    <aside {{ $attributes->class('ad-rail') }} aria-label="Sponsored">
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
    </aside>
@endif
