@props(['event'])

@php
    $specs = \App\Support\EventSpecRows::for($event);
    $planned = $event->isProvisional();
    $bannerUrl = $event->bannerUrl();
    $family = $event->primaryDiscipline()?->family->value ?? $event->disciplines->first()?->family->value;
@endphp

<article class="dope {{ $planned ? 'is-planned' : '' }}" data-fam="{{ $family }}">
    <div class="dope-banner {{ $bannerUrl ? '' : 'fallback' }}">
        @if ($bannerUrl)
            <img src="{{ $bannerUrl }}" alt="{{ $event->title }} match banner">
        @else
            <svg viewBox="0 0 40 40" aria-hidden="true">
                <circle cx="20" cy="20" r="17" fill="none" stroke="#D9AE52" stroke-width="1.2"/>
                <circle cx="20" cy="20" r="7" fill="none" stroke="#D9AE52" stroke-width=".9"/>
                <path d="M20 1v11M20 28v11M1 20h11M28 20h11" stroke="#D9AE52" stroke-width="1.2"/>
            </svg>
        @endif
        <div class="pill-row">
            <x-event-status-pill :event="$event" />
        </div>
        <span class="banner-caption">{{ $bannerUrl ? $event->title : 'No club banner yet' }}</span>
    </div>
    <div class="dope-top">
        <div>
            <h3>{{ $event->title }}</h3>
            <span class="club">{{ $event->hostOrganisation?->name }}</span>
        </div>
        <div class="dope-date">
            <b>{{ $event->starts_at->timezone('Africa/Johannesburg')->format('j') }}</b>
            {{ $event->starts_at->timezone('Africa/Johannesburg')->format('M y') }}
        </div>
    </div>
    <dl class="dope-rows">
        @foreach ($specs as [$label, $value])
            <div class="r">
                <dt>{{ $label }}</dt>
                <dd>{{ $value }}</dd>
            </div>
        @endforeach
    </dl>
    <div class="dope-foot">
        <a href="{{ route('matches.show', $event->slug) }}">Match details</a>
        @if ($event->entry_url)
            <a href="{{ $event->entry_url }}" rel="noopener noreferrer">Entry details</a>
        @else
            <span>No entry link yet</span>
        @endif
    </div>
</article>
