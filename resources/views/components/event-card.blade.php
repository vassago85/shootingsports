@props(['event'])

@php
    $specs = \App\Support\EventSpecRows::for($event);
    $planned = $event->isProvisional();
    $bannerUrl = $event->bannerUrl();
    $coverUrl = $event->coverImageUrl();
    $usingHostLogo = $coverUrl && ! $bannerUrl;
    $family = $event->primaryDiscipline()?->family->value ?? $event->disciplines->first()?->family->value;
@endphp

<article class="dope {{ $planned ? 'is-planned' : '' }}" data-fam="{{ $family }}">
    <div class="dope-banner {{ $coverUrl ? ($usingHostLogo ? 'logo-fallback' : 'has-poster') : 'fallback' }}">
        @if ($coverUrl && ! $usingHostLogo)
            {{-- Blurred, darkened copy of the poster fills the 3:1 crop so
                 posters that are square or portrait (most of them) stop
                 losing 88% of themselves to object-fit: cover. --}}
            <img class="dope-banner-bg" src="{{ $coverUrl }}" alt="" aria-hidden="true" loading="lazy" decoding="async">
            <img class="dope-banner-fg" src="{{ $coverUrl }}" alt="{{ $event->title.' match banner' }}" loading="lazy" decoding="async">
        @elseif ($coverUrl)
            {{-- Host-logo fallback: no backdrop, just the logo centred. --}}
            <img src="{{ $coverUrl }}" alt="{{ $event->hostOrganisation?->name.' logo' }}" loading="lazy" decoding="async">
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
        <span class="banner-caption">{{ $coverUrl ? $event->title : 'No match banner yet' }}</span>
    </div>
    <div class="dope-top">
        <div>
            <h3>{{ $event->title }}</h3>
            {{-- Falls back to the venue name when the range operator is
                 the host (no external club), so a card never shows an
                 empty ".club" line. --}}
            <span class="club">{{ $event->hostDisplayName() }}</span>
        </div>
        <div class="dope-date">
            <span class="dow">{{ \App\Support\EventDate::weekday($event->starts_at) }}</span>
            <b>{{ \App\Support\EventDate::dayOfMonth($event->starts_at) }}</b>
            <span class="mo">{{ \App\Support\EventDate::monthWithYear($event->starts_at) }}</span>
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
    @auth
        <livewire:save-to-calendar :event="$event" :key="'save-'.$event->id" />
    @else
        <a class="dope-save" href="{{ route('login') }}">Add to my calendar</a>
    @endauth
</article>
