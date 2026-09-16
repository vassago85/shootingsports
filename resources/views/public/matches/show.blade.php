<x-layouts.public
    :title="$event->title"
    :description="$event->hostDisplayName().' · '.$event->locationLabel().' · '.$event->starts_at->timezone('Africa/Johannesburg')->format('j F Y')"
    :json-ld="$jsonLd"
>
    <main id="main">
        <section class="page-hero">
            <div class="wrap">
                <p class="label">{{ $event->starts_at->timezone('Africa/Johannesburg')->format('l j F Y') }}</p>
                <h1>{{ $event->title }}</h1>
                <p>
                    {{ $event->hostDisplayName() }}
                    @if ($event->hostOrganisation && $event->venue && $event->hostOrganisation->name !== $event->venue->name)
                        · {{ $event->locationLabel() }}
                    @elseif (! $event->hostOrganisation)
                        · at the range
                    @else
                        · {{ $event->locationLabel() }}
                    @endif
                </p>
                <div class="pill-row" style="position:static;margin-top:16px">
                    <x-event-status-pill :event="$event" />
                </div>
            </div>
        </section>
        <section class="block">
            <div class="wrap" style="max-width:760px">
                @if ($bannerUrl = $event->bannerUrl())
                    <figure class="match-poster">
                        <img src="{{ $bannerUrl }}" alt="{{ $event->title }} match poster">
                    </figure>
                @endif
                <dl class="dope-rows" style="padding:0 0 24px">
                    @foreach ($specs as [$label, $value])
                        <div class="r"><dt>{{ $label }}</dt><dd>{{ $value }}</dd></div>
                    @endforeach
                    @if ($event->level)
                        <div class="r"><dt>Level</dt><dd>{{ $event->level->getLabel() }}</dd></div>
                    @endif
                </dl>
                @if ($event->description)
                    <div class="prose">{!! nl2br(e($event->description)) !!}</div>
                @endif

                {{-- Primary CTA: external entry link. Promoted above the
                     ghost secondaries because it's the money action —
                     the whole card journey funnels the shooter here.
                     Rendered only when the match has an entry URL; if
                     there isn't one, an explicit "no online entry"
                     line is shown instead of silently omitting so the
                     visitor knows the site isn't hiding a link. --}}
                <div class="match-entry-cta" style="margin-top:26px">
                    @if ($event->entry_url)
                        <a
                            class="btn"
                            href="{{ $event->entry_url }}"
                            rel="noopener noreferrer"
                            target="_blank"
                        >Enter here →</a>
                        <p class="match-entry-note">
                            Opens {{ parse_url($event->entry_url, PHP_URL_HOST) ?: 'the host\'s entry page' }} in a new tab.
                        </p>
                    @else
                        <p class="match-entry-note">
                            No online entry link on file for this match.
                            @if ($event->hostOrganisation)
                                Contact <a href="{{ $event->hostOrganisation->isFederationListing()
                                    ? route('federations.show', $event->hostOrganisation->slug)
                                    : route('clubs.show', $event->hostOrganisation->slug) }}">{{ $event->hostOrganisation->name }}</a> to enter.
                            @else
                                Ask at the range on the day.
                            @endif
                        </p>
                    @endif
                </div>

                <p style="margin-top:22px">
                    @auth
                        <livewire:save-to-calendar :event="$event" variant="button" :key="'save-match-'.$event->id" />
                        <livewire:log-attendance :event="$event" :key="'log-match-'.$event->id" />
                    @else
                        <a class="btn ghost" href="{{ route('login') }}">Add to my calendar</a>
                        <a class="btn ghost" href="{{ route('login') }}">I shot this</a>
                    @endauth
                    @if ($event->hostOrganisation)
                        @if ($event->hostOrganisation->isFederationListing())
                            <a class="btn ghost" href="{{ route('federations.show', $event->hostOrganisation->slug) }}">Host federation</a>
                        @else
                            <a class="btn ghost" href="{{ route('clubs.show', $event->hostOrganisation->slug) }}">Host club</a>
                        @endif
                    @endif
                    @if ($event->venue)
                        <a class="btn ghost" href="{{ route('ranges.show', $event->venue->slug) }}">Range</a>
                    @endif
                </p>
            </div>
        </section>
    </main>
</x-layouts.public>
