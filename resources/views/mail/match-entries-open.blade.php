<x-mail::message>
# Entries just opened: {{ $event->title }}

Hi {{ $user->name }},

Entries have opened for a match you saved.

@php
    $when = $event->starts_at?->copy()->timezone('Africa/Johannesburg');
    $venueName = $event->venue?->name;
    $venueTown = $event->venue?->town;
@endphp

- **When:** {{ $when?->format('l, j F Y')
    .($event->all_day ? '' : ' · '.$when?->format('H:i')) }}
@if ($venueName)
- **Where:** {{ $venueName }}{{ $venueTown ? ' · '.$venueTown : '' }}
@endif

<x-mail::button :url="route('events.show', $event->slug)">
Open the match
</x-mail::button>

Entry windows for popular matches fill fast. If this one is cap-limited,
you'll want to get in now.

@include('mail.partials.footer-marketing', ['category' => 'match alert'])
</x-mail::message>
