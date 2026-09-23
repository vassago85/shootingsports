@props(['src' => null, 'alt' => '', 'logo' => false, 'label' => null])
@php
    $initials = collect(preg_split('/\s+/', (string) $label))
        ->filter()
        ->take(2)
        ->map(fn (string $word): string => mb_strtoupper(mb_substr($word, 0, 1)))
        ->implode('');
@endphp
<span {{ $attributes->class(['v2-thumb', 'is-logo' => $logo && filled($src)]) }}>
    @if (filled($src))
        <img src="{{ $src }}" alt="{{ $alt }}">
    @elseif ($initials !== '')
        <b>{{ $initials }}</b>
    @else
        <svg viewBox="0 0 48 48" fill="none" stroke="currentColor" stroke-width="1.6" aria-hidden="true">
            <circle cx="24" cy="24" r="16"/>
            <circle cx="24" cy="24" r="7"/>
            <path d="M24 4v8M24 36v8M4 24h8M36 24h8"/>
        </svg>
    @endif
</span>
