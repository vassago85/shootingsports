@props([
    'page',
    'placementSlot',
    'limit' => 3,
    // When true, the slot renders nothing at all if there's no live ad.
    // Use this on high-traffic surfaces (home hero neighbourhood) where
    // an empty "Advertise here" panel makes a young directory read
    // as thin. Keep it false on /advertise and /calendar where the
    // vacant slot is doing genuine sales work.
    'hideWhenVacant' => false,
    'division' => null,
    'disciplines' => null,
])

@php
    $placements = $disciplines !== null
        ? \App\Support\AdPlacements::forDisciplines($disciplines, (int) $limit)
        : \App\Support\AdPlacements::for($page, $placementSlot, (int) $limit, $division);
@endphp

@if ($placements->isEmpty() && $hideWhenVacant)
    {{-- Silent: no chrome, no vacant pitch. --}}
@else
<aside {{ $attributes->class('ss-partner') }} aria-label="Partner">
    @if ($placements->isNotEmpty())
        <p class="ss-partner-label">Sponsored</p>
        <div class="ss-partner-stack">
            @foreach ($placements as $placement)
                @php
                    $placement->recordImpression();
                    $href = $placement->destinationUrl()
                        ? route('placements.click', $placement)
                        : null;
                    $img = $placement->imageUrl();
                    $alt = $placement->headline ?: ($placement->provider->name ?? '');
                @endphp
                <article @class(['ss-partner-card', 'ss-partner-banner' => filled($img)])>
                    @if ($img)
                        @if ($href)
                            <a href="{{ $href }}" rel="sponsored noopener noreferrer" class="ss-partner-media">
                                <img src="{{ $img }}" alt="{{ $alt }}">
                            </a>
                        @else
                            <div class="ss-partner-media"><img src="{{ $img }}" alt="{{ $alt }}"></div>
                        @endif
                    @else
                        <div class="ss-partner-copy">
                            @if ($placement->headline)
                                <h3>
                                    @if ($href)
                                        <a href="{{ $href }}" rel="sponsored noopener noreferrer">{{ $placement->headline }}</a>
                                    @else
                                        {{ $placement->headline }}
                                    @endif
                                </h3>
                            @elseif ($placement->provider && ($placement->provider->category === null || $placement->provider->category->isPublic()))
                                <h3>
                                    <a href="{{ $href ?? route('suppliers.show', $placement->provider->slug) }}">{{ $placement->provider->name }}</a>
                                </h3>
                            @elseif ($placement->provider)
                                <h3>{{ $placement->provider->name }}</h3>
                            @endif
                            @if ($placement->body)
                                <p>{{ $placement->body }}</p>
                            @endif
                        </div>
                    @endif
                </article>
            @endforeach
        </div>
    @else
        <a class="ss-partner-open" href="{{ route('advertise') }}">
            <p class="ss-partner-label">Advertising</p>
            <p class="ss-partner-kicker">This space is available</p>
            <h3>Advertise here</h3>
            <p>A quiet place on the register. Seen by clubs, match directors and shooters looking for the next match.</p>
            <span class="ss-partner-cta">Enquire about this space →</span>
        </a>
    @endif
</aside>
@endif
