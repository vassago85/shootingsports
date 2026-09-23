@props(['match'])

@php
    $townLine = collect([$match['town'] ?? null, $match['province'] ?? null])->filter()->implode(', ');
    $place = collect([$match['range'] ?? null, $townLine])->filter()->implode(' · ');
    $levelValue = $match['level_value'] ?? null;
    $levelBadge = in_array($levelValue, ['national', 'provincial', 'international'], true) ? $match['level'] : null;
    $beginner = in_array('new-shooter-friendly', $match['flag_slugs'] ?? [], true);
    $facts = collect([
        ($match['rounds'] ?? null) ? $match['rounds'].' rounds' : null,
        $match['distance'] ?? null,
        $match['fee'] ?? null,
        ($match['status_value'] ?? null) === 'entries_open' ? 'Registration open' : null,
        isset($match['distance_km']) && $match['distance_km'] !== null ? round($match['distance_km']).' km' : null,
    ])->filter()->values();
@endphp

<a class="mk-match" href="{{ $mk('mockups.match', ['slug' => $match['slug']]) }}">
    <span class="mk-date">
        <b>{{ $match['day'] }}</b>
        <span>{{ $match['month'] }}</span>
    </span>
    <span class="mk-match-copy">
        <span class="mk-match-top">
            <span class="mk-title">{{ $match['title'] }}</span>
            @if ($levelBadge || $beginner)
                <span class="mk-match-flags">
                    @if ($levelBadge)<span class="mk-badge">{{ $levelBadge }}</span>@endif
                    @if ($beginner)<span class="mk-badge">Beginner friendly</span>@endif
                </span>
            @endif
        </span>
        @if ($match['discipline'] ?? null)
            <span class="mk-sport">{{ $match['discipline'] }}</span>
        @endif
        @if ($place !== '')
            <span class="mk-place">{{ $place }}</span>
        @endif
        @if (($match['organiser'] ?? null) || $facts->isNotEmpty())
            <span class="mk-meta">
                @if ($match['organiser'] ?? null)
                    <span>{{ $match['organiser'] }}</span>
                @endif
                @foreach ($facts as $fact)
                    <span>{{ $fact }}</span>
                @endforeach
            </span>
        @endif
    </span>
</a>
