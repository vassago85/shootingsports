@props(['listing'])

@php
    $state = $listing->verification_state ?? null;
    $at = $listing->last_verified_at;
    $text = match (true) {
        $at === null => 'Unconfirmed',
        $state === \App\Enums\VerificationState::Ageing => 'Last confirmed '.$at->timezone('Africa/Johannesburg')->format('F Y'),
        default => 'Confirmed '.$at->timezone('Africa/Johannesburg')->format('F Y'),
    };
@endphp

<span {{ $attributes->class('badge-verified') }}>{{ $text }}</span>
