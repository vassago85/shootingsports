@props(['event'])

@php
    $specs = \App\Support\EventSpecRows::for($event);
    $planned = $event->isProvisional();
    $bannerUrl = $event->bannerUrl();
    $coverUrl = $event->coverImageUrl();
    $usingHostLogo = $coverUrl && ! $bannerUrl;
    $family = $event->primaryDiscipline()?->family->value ?? $event->disciplines->first()?->family->value;
    $matchUrl = route('matches.show', $event->slug);
@endphp

{{--
    Whole-card link via the title-anchor + ::after overlay pattern:
    the h3's anchor stretches invisibly across the entire article,
    so clicking anywhere navigates to the match detail page. Inner
    interactive elements (entry link, add-to-calendar) get
    `position: relative; z-index: 1` in the CSS so they sit above
    the overlay and still fire their own clicks — without nesting
    anchors, which is invalid HTML.
--}}
<article class="dope {{ $planned ? 'is-planned' : '' }}" data-fam="{{ $family }}">
    {{-- Discipline-family accent tick (cool-factor). Purely visual,
         so aria-hidden. The colour comes from the `--fam-accent`
         custom property set by [data-fam="..."] on the article. --}}
    <div class="dope-fam-tick" aria-hidden="true"></div>
    <div class="dope-banner {{ $coverUrl ? ($usingHostLogo ? 'logo-fallback' : 'has-poster') : 'fallback' }}">
        @if ($coverUrl && ! $usingHostLogo)
            {{-- Blurred, darkened copy of the poster fills the 3:1 crop
                 so square / portrait posters (most of them) do not lose
                 88% of themselves to object-fit: cover. --}}
            <img class="dope-banner-bg" src="{{ $coverUrl }}" alt="" aria-hidden="true" loading="lazy" decoding="async">
            <img class="dope-banner-fg" src="{{ $coverUrl }}" alt="" loading="lazy" decoding="async">
        @elseif ($coverUrl)
            {{-- Host-logo fallback: no backdrop, just the logo centred. --}}
            <img src="{{ $coverUrl }}" alt="{{ $event->hostOrganisation?->name.' logo' }}" loading="lazy" decoding="async">
        @else
            {{-- No poster, no host logo: the reticle plate is the identity. --}}
            <svg viewBox="0 0 40 40" aria-hidden="true">
                <circle cx="20" cy="20" r="17" fill="none" stroke="#D9AE52" stroke-width="1.2"/>
                <circle cx="20" cy="20" r="7" fill="none" stroke="#D9AE52" stroke-width=".9"/>
                <path d="M20 1v11M20 28v11M1 20h11M28 20h11" stroke="#D9AE52" stroke-width="1.2"/>
            </svg>
        @endif
        <div class="pill-row">
            <x-event-status-pill :event="$event" />
        </div>
        {{-- Deliberately no banner caption: the h3 in dope-top is the
             title. Printing it twice was ugly on posters and collided
             with host logos. --}}
    </div>
    <div class="dope-top">
        <div>
            <h3>
                {{-- The title anchor is the primary/whole-card link.
                     `.dope-title-link::after` in CSS overlays the entire
                     article so a click anywhere on the card lands on
                     matches.show — without nesting <a> tags. --}}
                <a href="{{ $matchUrl }}" class="dope-title-link">{{ $event->title }}</a>
            </h3>
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

    {{-- Footer CTA: internal-first. "Entry details" points to the
         match page (not the external entry form) — the match page is
         where the actual details live (specs, description, poster,
         host + venue links), and it's where the prominent "Enter here"
         button sends the click on to the external URL.
         Rendered only when there IS an entry URL, so the CTA promise
         ("click to see how to enter") is always kept. Matches without
         an entry link stay clickable via the whole-card overlay but
         don't advertise a false affordance. --}}
    @if ($event->entry_url)
        <div class="dope-foot">
            <a href="{{ $matchUrl }}" class="dope-primary">Entry details</a>
        </div>
    @endif

    {{-- Add-to-calendar sits below the footer as a ghost secondary. --}}
    @auth
        <livewire:save-to-calendar :event="$event" :key="'save-'.$event->id" />
    @else
        <a class="dope-save" href="{{ route('login') }}">Add to my calendar</a>
    @endauth
</article>
