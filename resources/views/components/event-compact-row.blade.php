@props(['event'])

@php
    $matchUrl = route('events.show', $event->slug);
    $discipline = $event->primaryDiscipline() ?? $event->disciplines->first();
    $distance = $discipline?->typical_distances;
    $hostName = $event->listedHost();
@endphp

<article class="match-mini">
    <div class="match-mini-date">
        <b>{{ \App\Support\EventDate::dayOfMonth($event->starts_at, $event->ends_at) }}</b>
        <span>{{ \App\Support\EventDate::monthWithYear($event->starts_at, $event->ends_at) }}</span>
    </div>
    <div class="match-mini-main">
        <a href="{{ $matchUrl }}">{{ $event->title }}</a>
        @if ($hostName)
            <span>{{ $hostName }}</span>
        @endif
    </div>
    @if (filled($distance))
        <div class="match-mini-distance">{{ $distance }}</div>
    @endif
    <a class="match-mini-go" href="{{ $matchUrl }}">View match →</a>
</article>
