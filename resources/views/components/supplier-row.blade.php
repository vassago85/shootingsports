@props(['provider'])

<a {{ $attributes->class('dir-row supplier-row') }} href="{{ route('suppliers.show', $provider->slug) }}">
    @if ($provider->logoUrl())
        <img class="dir-logo" src="{{ $provider->logoUrl() }}" alt="">
    @else
        <span class="dir-initials">{{ $provider->initials() }}</span>
    @endif
    <span class="dir-main">
        <strong>{{ $provider->name }} <x-listing-tier-badge :listing="$provider" /></strong>
        @if ($summary = $provider->cardSummary())
            <span class="dir-blurb">{{ $summary }}</span>
        @endif
        <span>{{ collect([$provider->town, $provider->province?->getLabel()])->filter()->implode(' · ') }}</span>
        <x-verification-badge :listing="$provider" />
    </span>
    <span class="dir-chev" aria-hidden="true">›</span>
</a>
