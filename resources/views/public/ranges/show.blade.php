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
                @php $photos = $venue->imageUrls(); @endphp
                <div class="range-profile">
                    <div>
                @if ($photos !== [])
                    <div class="range-photos">
                        @foreach ($photos as $photo)
                            <figure class="range-photo">
                                <img src="{{ $photo }}" alt="{{ $venue->photoAlt($photo) }}">
                                @if ($caption = $venue->photoCaption($photo))
                                    <figcaption>{{ $caption }}</figcaption>
                                @endif
                            </figure>
                        @endforeach
                    </div>
                @endif
                @if (filled($venue->description))
                    <section class="range-about">
                        <p class="label">Profile</p>
                        <h2>About {{ $venue->name }}</h2>
                        <div class="supplier-prose">
                            @foreach (preg_split("/\n{2,}/", trim($venue->description)) ?: [] as $paragraph)
                                @if (filled(trim($paragraph)))
                                    <p>{{ trim($paragraph) }}</p>
                                @endif
                            @endforeach
                        </div>
                    </section>
                @endif
                @php
                    // Access is a promise to visitors — "Guest by
                    // arrangement" only shows when an operator has
                    // confirmed it, not on the auto-fill of every
                    // fresh listing.
                    $accessIsTrusted = $venue->access
                        && in_array(
                            $venue->verification_state,
                            [\App\Enums\VerificationState::Verified, \App\Enums\VerificationState::Ageing],
                            true,
                        );
                @endphp
                @if ($venue->max_distance_m || $venue->bay_count || $accessIsTrusted || $venue->day_fee_cents || $venue->metro)
                <dl class="dope-rows" style="max-width:420px;padding:0 0 28px">
                    @if ($venue->max_distance_m)
                        <div class="r"><dt>Max distance</dt><dd>{{ number_format($venue->max_distance_m) }} m</dd></div>
                    @endif
                    @if ($venue->bay_count)
                        <div class="r"><dt>Bays</dt><dd>{{ $venue->bay_count }}</dd></div>
                    @endif
                    @if ($accessIsTrusted)
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
                @php
                    // `facilities` is a JSON list, so `filled()` alone
                    // isn't enough — a saved [] round-trips as an empty
                    // array. Only render when there is at least one
                    // truthy entry.
                    $facilities = collect($venue->facilities ?? [])
                        ->filter(fn ($item): bool => filled($item))
                        ->values();
                @endphp
                @if ($facilities->isNotEmpty())
                    <section class="range-facilities" style="margin:0 0 24px">
                        <p class="label">On site</p>
                        <ul style="list-style:disc;padding-left:20px;margin:0;display:grid;gap:4px;color:var(--slate)">
                            @foreach ($facilities as $facility)
                                <li>{{ $facility }}</li>
                            @endforeach
                        </ul>
                    </section>
                @endif
                <p style="margin:0 0 28px;display:flex;gap:10px;flex-wrap:wrap">
                    <a class="btn" href="{{ route('enquiries.listing', ['type' => 'venue', 'id' => $venue->id]) }}">Enquire via platform</a>
                    @if ($venue->website_url)
                        <a class="btn ghost" href="{{ $venue->website_url }}" rel="noopener noreferrer">Website</a>
                    @endif
                    <a class="btn ghost" href="{{ $venue->directionsUrl() }}" rel="noopener noreferrer" target="_blank">Directions</a>
                </p>
                    </div>
                    @if ($venue->logoUrl())
                        <div class="supplier-logo-card range-mark">
                            <img src="{{ $venue->logoUrl() }}" alt="{{ $venue->name }} logo">
                        </div>
                    @elseif ($photos === [])
                        <div class="supplier-logo-card range-mark">
                            <span class="supplier-initials" aria-hidden="true">{{ $venue->initials() }}</span>
                        </div>
                    @endif
                </div>
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

                <x-owner-panel title="For range operators">
                    <p>Manage this range — update details, publish events at this venue, or embed the calendar on your site.</p>
                    <div class="owner-actions">
                        @if ($venue->claimed_by === null)
                            <a class="btn ghost" href="{{ route('listings.claim', ['type' => 'range', 'slug' => $venue->slug]) }}">This is my range</a>
                        @endif
                        <a class="btn ghost" href="{{ route('login') }}?redirect={{ urlencode(url()->current()) }}">Operator login</a>
                    </div>
                    <div class="owner-embed">
                        <x-embed-snippet :venue="$venue->slug" />
                    </div>
                </x-owner-panel>
            </div>
        </section>
    </main>
</x-layouts.public>
