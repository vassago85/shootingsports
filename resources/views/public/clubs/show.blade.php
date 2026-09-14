<x-layouts.public
    :title="$organisation->name"
    :description="$organisation->description ?: $organisation->name.' — '.$organisation->province?->getLabel()"
    :json-ld="$jsonLd"
>
    <main id="main">
        <section class="page-hero">
            <div class="wrap">
                <p class="label">{{ $organisation->type->getLabel() }} · {{ $organisation->province?->getLabel() }}</p>
                <div class="hero-row">
                    @if ($organisation->logoUrl())
                        <img class="hero-logo" src="{{ $organisation->logoUrl() }}" alt="{{ $organisation->name }} logo" width="96" height="96">
                    @endif
                    <div>
                        <h1>{{ $organisation->name }}</h1>
                        <p>{{ $organisation->description }}</p>
                        <x-verification-badge :listing="$organisation" />
                    </div>
                </div>
            </div>
        </section>
        <section class="block">
            <div class="wrap">
                <p class="meta" style="font-family:var(--f-mono);font-size:13px;color:var(--slate)">
                    @if ($organisation->town){{ $organisation->town }} · @endif
                    {{ $organisation->province?->getLabel() }}
                    @if ($organisation->visitors_welcome) · Visitors welcome @endif
                </p>
                @if ($organisation->website_url)
                    <p><a class="label" href="{{ $organisation->website_url }}" rel="noopener noreferrer" style="border-bottom:1px solid var(--brass);text-decoration:none">{{ parse_url($organisation->website_url, PHP_URL_HOST) }} →</a></p>
                @endif
                <p style="margin:14px 0 0">
                    <a class="btn" href="{{ route('enquiries.listing', ['type' => 'organisation', 'id' => $organisation->id]) }}">Enquire via platform</a>
                </p>
                <div class="club-tags" style="margin:16px 0 28px">
                    @foreach ($organisation->disciplines as $discipline)
                        <a class="tag" href="{{ route('disciplines.show', $discipline->slug) }}">{{ $discipline->name }}</a>
                    @endforeach
                </div>

                <div class="sec-head">
                    <p class="label">Calendar</p>
                    <h2>Upcoming matches</h2>
                </div>
                @if ($events->isEmpty())
                    <p class="empty">No upcoming matches listed.</p>
                @else
                    <div class="dope-grid">
                        @foreach ($events as $event)
                            <x-event-card :event="$event" />
                        @endforeach
                    </div>
                @endif
                <p style="margin-top:18px">
                    <a class="label" href="{{ route('ical.organisation', $organisation->slug) }}" style="text-decoration:none;border-bottom:1px solid var(--brass)">Subscribe via iCal →</a>
                </p>
            </div>
        </section>
    </main>
</x-layouts.public>
