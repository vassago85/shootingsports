<x-layouts.public
    :title="$event->title"
    :description="$event->hostOrganisation?->name.' · '.$event->locationLabel().' · '.$event->starts_at->timezone('Africa/Johannesburg')->format('j F Y')"
    :json-ld="$jsonLd"
>
    <main id="main">
        <section class="page-hero">
            <div class="wrap">
                <p class="label">{{ $event->starts_at->timezone('Africa/Johannesburg')->format('l j F Y') }}</p>
                <h1>{{ $event->title }}</h1>
                <p>{{ $event->hostOrganisation?->name }} · {{ $event->locationLabel() }}</p>
                <div class="pill-row" style="position:static;margin-top:16px">
                    <x-event-status-pill :event="$event" />
                </div>
            </div>
        </section>
        <section class="block">
            <div class="wrap" style="max-width:760px">
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
