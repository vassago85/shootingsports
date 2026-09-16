@props(['listing'])

@php
    $state = $listing->verification_state ?? null;
    $at = $listing->last_verified_at;

    // UX audit Bundle A #3: Unconfirmed is the default for every new
    // listing — printing it on almost every club/range row makes the
    // register look untrusted. Render nothing until someone confirms.
    if ($at === null) {
        return;
    }

    [$text, $variant] = match (true) {
        $state === \App\Enums\VerificationState::Ageing => ['Last confirmed '.$at->timezone('Africa/Johannesburg')->format('F Y'), 'ageing'],
        default => ['Confirmed '.$at->timezone('Africa/Johannesburg')->format('F Y'), 'verified'],
    };
@endphp

<span {{ $attributes->class(['badge-verified', 'badge-verified--'.$variant]) }}>{{ $text }}</span>
