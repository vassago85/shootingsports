@props(['listing'])

@php
    $state = $listing->verification_state ?? null;
    $at = $listing->last_verified_at;

    // Emphasis flips with earned status. Unconfirmed goes silent (default
    // for every new listing) so gold reads as "someone actually confirmed
    // this", not the noise the reviewer flagged.
    [$text, $variant] = match (true) {
        $at === null => ['Unconfirmed', 'unconfirmed'],
        $state === \App\Enums\VerificationState::Ageing => ['Last confirmed '.$at->timezone('Africa/Johannesburg')->format('F Y'), 'ageing'],
        default => ['Confirmed '.$at->timezone('Africa/Johannesburg')->format('F Y'), 'verified'],
    };
@endphp

<span {{ $attributes->class(['badge-verified', 'badge-verified--'.$variant]) }}>{{ $text }}</span>
