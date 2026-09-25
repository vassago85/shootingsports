<x-layouts.public
    :title="$seo->title"
    :description="$seo->description"
    :canonical="$seo->canonical"
    :robots="$seo->robots"
    :image="$venue->imageUrls()[0] ?? null"
    :json-ld="$jsonLd"
>
    <main id="main">
        <section class="page-hero">
            <div class="wrap">
                <p class="label">{{ collect(['Range', $venue->province?->getLabel()])->filter()->implode(' · ') }}</p>
                <h1>{{ $venue->name }} <x-listing-tier-badge :listing="$venue" /></h1>
                @if (filled($venue->address ?: $venue->town))
                    <p>{{ $venue->address ?: $venue->town }}</p>
                @endif
                <x-verification-badge :listing="$venue" />
            </div>
        </section>
        <section class="block">
            <div class="wrap">
                @if ($photos = $venue->imageUrls())
                    <div class="range-photos">
                        @foreach ($photos as $photo)
                            <img src="{{ $photo }}" alt="{{ $venue->name }}">
                        @endforeach
                    </div>
                @endif
                @if ($venue->max_distance_m || $venue->bay_count || $venue->access || $venue->day_fee_cents || $venue->metro)
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
                @endif
                <p style="margin:0 0 28px">
                    <a class="btn" href="{{ route('enquiries.listing', ['type' => 'venue', 'id' => $venue->id]) }}">Enquire via platform</a>
                </p>
                @if ($inferredDisciplines->isNotEmpty())
                    <div class="club-tags" style="margin-bottom:28px">
                        @foreach ($inferredDisciplines as $discipline)
                            <a class="tag" href="{{ route('disciplines.show', $discipline->slug) }}">{{ $discipline->name }}</a>
                        @endforeach
                    </div>
                @endif
                @if ($events->isNotEmpty())
                    <div class="sec-head">
                        <p class="label">Calendar</p>
                        <h2>Matches at this range</h2>
                    </div>
                    <div class="dope-grid">
                        @foreach ($events as $event)
                            <x-event-card :event="$event" />
                        @endforeach
                    </div>
                @endif

                @if ($clubsUsingRange->isNotEmpty())
                    <div class="sec-head" style="margin-top:36px">
                        <p class="label">Regulars</p>
                        <h2>Clubs using this range</h2>
                    </div>
                    <ul class="range-clubs" style="list-style:none;padding:0;margin:0 0 28px;display:grid;gap:8px">
                        @foreach ($clubsUsingRange as $club)
                            <li>
                                <a href="{{ $club->isFederationListing() ? route('federations.show', $club->slug) : route('clubs.show', $club->slug) }}">{{ $club->name }}</a>
                                @if ($club->province)
                                    <span style="color:var(--slate);font-family:var(--f-mono);font-size:13px"> · {{ $club->province->code() }}</span>
                                @endif
                            </li>
                        @endforeach
                    </ul>
                @endif

                <x-embed-snippet :venue="$venue->slug" />
            </div>
        </section>
    </main>
</x-layouts.public>
