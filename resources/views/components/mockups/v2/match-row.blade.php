@props(['match'])
@php
    $place = collect([$match['range'] ?? null, collect([$match['town'] ?? null, $match['province'] ?? null])->filter(fn (?string $part): bool => filled($part) && $part !== '—')->implode(', ')])
        ->filter(fn (?string $part): bool => filled($part) && $part !== '—')
        ->implode(' · ');
    $level = in_array($match['level_value'] ?? null, ['national', 'provincial', 'international', 'club'], true) ? $match['level'] : null;
    $beginner = in_array('new-shooter-friendly', $match['flag_slugs'] ?? [], true);
    $side = collect([
        ['clock', ($match['time'] ?? null) === 'All day' ? 'All day' : ($match['time'] ?? null)],
        ['target', filled($match['rounds'] ?? null) ? $match['rounds'].' rounds' : null],
        ['ruler', $match['distance'] ?? null],
        ['ticket', ($match['status_value'] ?? null) === 'entries_open' ? 'Registration open' : null],
    ])->filter(fn (array $item): bool => filled($item[1]));
@endphp
<a class="v2-match" href="{{ $mk('mockups.v2.match', ['slug' => $match['slug']]) }}">
    <span class="v2-date">
        <b>{{ $match['day'] }}</b>
        <span>{{ $match['month'] }}</span>
    </span>
    <span>
        <span class="v2-match-title">
            {{ $match['title'] }}
            @if ($level)<span class="v2-badge">{{ $level }}</span>@endif
            @if ($beginner)<span class="v2-badge">Beginner friendly</span>@endif
        </span>
        @if (filled($match['discipline'] ?? null))
            <span class="v2-sport">{{ $match['discipline'] }}</span>
        @endif
        @if ($place !== '')
            <span class="v2-meta"><x-mockups.v2.icon name="pin" /> {{ $place }}</span>
        @endif
        @if (filled($match['organiser'] ?? null))
            <span class="v2-meta"><x-mockups.v2.icon name="people" /> {{ $match['organiser'] }}</span>
        @endif
    </span>
    <span class="v2-side">
        @foreach ($side as [$icon, $label])
            <span><x-mockups.v2.icon name="{{ $icon }}" /> {{ $label }}</span>
        @endforeach
    </span>
    <span class="v2-chev" aria-hidden="true">›</span>
</a>
