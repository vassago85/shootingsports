<x-layouts.public
    :title="$venue->name"
    :description="$venue->town.' · '.$venue->province?->getLabel().' shooting range.'"
    :json-ld="$jsonLd"
>
    <main id="main">
        <section class="page-hero">
            <div class="wrap">
                <p class="label">Range · {{ $venue->province?->getLabel() }}</p>
                <h1>{{ $venue->name }} <x-listing-tier-badge :listing="$venue" /></h1>
                <p>{{ $venue->address ?: $venue->town }}</p>
                <x-verification-badge :listing="$venue" />
            </div>
        </section>
        <section class="block">
            <div class="wrap">
                <dl class="dope-rows" style="max-width:420px;padding:0 0 28px">
                    @if ($venue->max_distance_m)
                        <div class="r"><dt>Max distance</dt><dd>{{ number_format($venue->max_distance_m) }} m</dd></div>
                    @endif
                    @if ($venue->bay_count)
                        <div class="r"><dt>Bays</dt><dd>{{ $venue->bay_count }}</dd></div>
                    @endif
                    @if ($venue->access)
                        <div class="r"><dt>Access</dt><dd>{{ $venue->access->getLabel() }}</dd></div>
                    @endif
                    @if ($venue->day_fee_cents)
                        <div class="r"><dt>Day fee</dt><dd>{{ \App\Support\Money::rand($venue->day_fee_cents) }}</dd></div>
                    @endif
                    @if ($venue->metro)
                        <div class="r"><dt>Metro</dt><dd>{{ $venue->metro->getLabel() }}</dd></div>
                    @endif
                </dl>
                <p style="margin:0 0 28px">
                    <a class="btn" href="{{ route('enquiries.listing', ['type' => 'venue', 'id' => $venue->id]) }}">Enquire via platform</a>
                </p>
                <div class="club-tags" style="margin-bottom:28px">
                    @foreach ($venue->disciplines as $discipline)
                        <a class="tag" href="{{ route('disciplines.show', $discipline->slug) }}">{{ $discipline->name }}</a>
                    @endforeach
                </div>
                <div class="sec-head">
                    <p class="label">Calendar</p>
                    <h2>Matches at this range</h2>
                </div>
                @if ($events->isEmpty())
                    <p class="empty">No upcoming matches at this range.</p>
                @else
                    <div class="dope-grid">
                        @foreach ($events as $event)
                            <x-event-card :event="$event" />
                        @endforeach
                    </div>
                @endif
            </div>
        </section>
    </main>
</x-layouts.public>
