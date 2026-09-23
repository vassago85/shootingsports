@props(['event'])

@php
    $specs = \App\Support\EventSpecRows::for($event);
    $planned = $event->isProvisional();
    $bannerUrl = $event->bannerUrl();
    $coverUrl = $event->coverImageUrl();
    $usingHostLogo = $coverUrl && ! $bannerUrl;
    $discipline = $event->primaryDiscipline() ?? $event->disciplines->first();
    $family = $discipline?->family->value;
    $matchUrl = route('matches.show', $event->slug);
    $venue = $event->venue;
    $venueName = $venue?->name;
    $venuePlace = collect([
        $venue?->town,
        $venue?->province?->getLabel() ?? $venue?->province?->code(),
    ])->filter()->implode(' · ');
    $fee = \App\Support\Money::rand($event->entry_fee_cents);
    $rounds = $event->round_count ? $event->round_count.' rounds' : null;
    $highlight = collect([$rounds, $fee])->filter()->implode(' · ');
    // Spec rows already cover venue / rounds / fee — drop those labels
    // from the dense list so the card hierarchy stays readable.
    $secondarySpecs = collect($specs)
        ->reject(fn (array $row): bool => in_array($row[0], ['Venue', 'Rounds', 'Min rounds', 'Entry', 'Discipline'], true))
        ->values()
        ->all();
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
    <div class="dope-fam-tick" aria-hidden="true"></div>
    <div class="dope-banner {{ $coverUrl ? ($usingHostLogo ? 'logo-fallback' : 'has-poster') : 'fallback' }}">
        @if ($coverUrl && ! $usingHostLogo)
            <img class="dope-banner-bg" src="{{ $coverUrl }}" alt="" aria-hidden="true" loading="lazy" decoding="async">
            <img class="dope-banner-fg" src="{{ $coverUrl }}" alt="" loading="lazy" decoding="async">
        @elseif ($coverUrl)
            <img src="{{ $coverUrl }}" alt="{{ $event->hostOrganisation?->name.' logo' }}" loading="lazy" decoding="async">
        @else
            <svg viewBox="0 0 40 40" aria-hidden="true">
                <circle cx="20" cy="20" r="17" fill="none" stroke="#879A4A" stroke-width="1.2"/>
                <circle cx="20" cy="20" r="7" fill="none" stroke="#879A4A" stroke-width=".9"/>
                <path d="M20 1v11M20 28v11M1 20h11M28 20h11" stroke="#879A4A" stroke-width="1.2"/>
            </svg>
        @endif
        <div class="pill-row">
            <x-event-status-pill :event="$event" />
        </div>
    </div>
    <div class="dope-top">
        <div>
            <h3>
                <a href="{{ $matchUrl }}" class="dope-title-link">{{ $event->title }}</a>
            </h3>
            @if ($listedHost = $event->listedHost())
                <span class="club">{{ $listedHost }}</span>
            @endif
        </div>
        <div class="dope-date {{ \App\Support\EventDate::isMultiDay($event->starts_at, $event->ends_at) ? 'is-range' : '' }}">
            <span class="dow">{{ \App\Support\EventDate::weekday($event->starts_at, $event->ends_at) }}</span>
            <b>{{ \App\Support\EventDate::dayOfMonth($event->starts_at, $event->ends_at) }}</b>
            <span class="mo">{{ \App\Support\EventDate::monthWithYear($event->starts_at, $event->ends_at) }}</span>
        </div>
    </div>

    <div class="dope-where">
        @if ($venueName)
            <div class="dope-venue">{{ $venueName }}</div>
        @endif
        @php $placeLine = $venuePlace !== '' ? $venuePlace : $event->listedLocation(); @endphp
        @if (filled($placeLine))
            <div class="dope-place">{{ $placeLine }}</div>
        @endif
        @if ($highlight !== '')
            <div class="dope-highlight">{{ $highlight }}</div>
        @endif
    </div>

    <div class="dope-tags">
        @if ($discipline)
            <span class="dope-tag">{{ $discipline->name }}</span>
        @endif
        @foreach ($event->flags as $flag)
            @if ($flag->slug === 'new-shooter-friendly')
                <span class="dope-tag is-novice">{{ $flag->name }}</span>
            @endif
        @endforeach
    </div>

    @if ($secondarySpecs !== [])
        <dl class="dope-rows">
            @foreach ($secondarySpecs as [$label, $value])
                <div class="r">
                    <dt>{{ $label }}</dt>
                    <dd>{{ $value }}</dd>
                </div>
            @endforeach
        </dl>
    @endif

    <div class="dope-foot">
        <a href="{{ $matchUrl }}" class="dope-primary">View match →</a>
    </div>

    @auth
        <livewire:save-to-calendar :event="$event" :key="'save-'.$event->id" />
    @else
        <a class="dope-save" href="{{ route('login') }}">Add to my calendar</a>
    @endauth
</article>
