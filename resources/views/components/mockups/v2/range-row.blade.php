@props(['range'])
@php
    $place = collect([$range['town'] ?? null, $range['province'] ?? null])->filter(fn (?string $part): bool => filled($part) && $part !== '—')->implode(' · ');
    $tags = array_slice($range['disciplines'] ?? [], 0, 4);
    $href = $mk('mockups.v2.range', ['slug' => $range['slug']]);
@endphp
<article
    class="v2-row v2-range"
    id="range-{{ $range['slug'] }}"
    data-slug="{{ $range['slug'] }}"
    @if ($range['has_gps'] ?? false) data-lat="{{ $range['lat'] }}" data-lng="{{ $range['lng'] }}" @endif
>
    <x-mockups.v2.thumb :label="$range['name']" />
    <a class="v2-row-main" href="{{ $href }}">
        <span class="v2-name">{{ $range['name'] }}</span>
        @if ($place !== '')
            <span class="v2-meta"><x-mockups.v2.icon name="pin" /> {{ $place }}</span>
        @endif
        @if ($tags !== [])
            <span class="v2-tags">
                @foreach ($tags as $tag)
                    <span class="v2-tag">{{ $tag }}</span>
                @endforeach
            </span>
        @endif
    </a>
    <span class="v2-side">
        @if (filled($range['max_distance'] ?? null))
            <span><x-mockups.v2.icon name="ruler" /> {{ $range['max_distance'] }}</span>
        @endif
        @if (filled($range['access'] ?? null))
            <span><x-mockups.v2.icon name="people" /> {{ $range['access'] }}</span>
        @endif
        @if (($range['upcoming_count'] ?? 0) > 0)
            <span><x-mockups.v2.icon name="calendar" /> {{ $range['upcoming_count'] }} upcoming {{ \Illuminate\Support\Str::plural('match', $range['upcoming_count']) }}</span>
        @endif
    </span>
    <a class="v2-chev" href="{{ $href }}" aria-label="Open {{ $range['name'] }}">›</a>
</article>
