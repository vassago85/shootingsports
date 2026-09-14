@props(['listing'])

@php
    $tier = $listing->tier ?? null;
    $show = $tier instanceof \App\Enums\ProviderTier
        && $tier !== \App\Enums\ProviderTier::Free;
@endphp

@if ($show)
    <span {{ $attributes->class(['badge-tier', 'badge-tier--'.$tier->value]) }}>{{ $tier->getLabel() }}</span>
@endif
