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
                    @if ($event->entry_url)
                        <a class="btn" href="{{ $event->entry_url }}" rel="noopener noreferrer">Entry details</a>
                    @endif
                </p>
            </div>
        </section>
    </main>
</x-layouts.public>
