@props(['club'])
@php
    $place = collect([$club['town'] ?? null, $club['province'] ?? null])->filter(fn (?string $part): bool => filled($part) && $part !== '—')->implode(' · ');
    if ($place === '' && filled($club['place'] ?? null) && $club['place'] !== '—') {
        $place = $club['place'];
    }
    $tags = array_slice($club['disciplines'] ?? [], 0, 4);
@endphp
<a class="v2-row v2-club" href="{{ $mk('mockups.v2.club', ['slug' => $club['slug']]) }}">
    <x-mockups.v2.thumb :src="$club['logo'] ?? null" :alt="$club['name']" :label="$club['name']" logo />
    <span>
        <span class="v2-name">{{ $club['name'] }}</span>
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
    </span>
    <span class="v2-side">
        @if (($club['upcoming_count'] ?? 0) > 0)
            <span><x-mockups.v2.icon name="calendar" /> {{ $club['upcoming_count'] }} upcoming {{ \Illuminate\Support\Str::plural('match', $club['upcoming_count']) }}</span>
        @endif
        @if ($club['visitors_welcome'] ?? false)
            <span><x-mockups.v2.icon name="people" /> Visitors welcome</span>
        @endif
    </span>
    <span class="v2-chev" aria-hidden="true">›</span>
</a>
