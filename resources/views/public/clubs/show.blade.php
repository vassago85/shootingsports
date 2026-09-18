<x-layouts.public
    :title="$seo->title"
    :description="$seo->description"
    :canonical="$seo->canonical"
    :robots="$seo->robots"
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
                <p style="margin:14px 0 0; display:flex; gap:10px; flex-wrap:wrap">
                    <a class="btn" href="{{ route('enquiries.listing', ['type' => 'organisation', 'id' => $organisation->id]) }}">Enquire via platform</a>
                    <livewire:follow-button
                        type="organisation"
                        :id="$organisation->id"
                        label="Follow this club"
                        :key="'follow-org-'.$organisation->id"
                    />
                </p>
                <div class="club-tags" style="margin:16px 0 28px">
                    @foreach ($inferredDisciplines as $discipline)
                        <a class="tag" href="{{ $organisation->province ? route('clubs.landing', [$organisation->province->urlSlug(), $discipline->slug]) : route('disciplines.show', $discipline->slug) }}">{{ $discipline->name }}</a>
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

                @if ($commonRanges->isNotEmpty())
                    <div class="sec-head" style="margin-top:36px">
                        <p class="label">Where they shoot</p>
                        <h2>Ranges commonly used</h2>
                    </div>
                    <ul class="club-ranges" style="list-style:none;padding:0;margin:0 0 28px;display:grid;gap:8px">
                        @foreach ($commonRanges as $range)
                            <li>
                                <a href="{{ route('ranges.show', $range->slug) }}">{{ $range->name }}</a>
                                @if ($range->town || $range->province)
                                    <span style="color:var(--slate);font-family:var(--f-mono);font-size:13px"> · {{ collect([$range->town, $range->province?->code()])->filter()->implode(' · ') }}</span>
                                @endif
                            </li>
                        @endforeach
                    </ul>
                @endif

                <x-embed-snippet :club="$organisation->slug" />
            </div>
        </section>
    </main>
</x-layouts.public>
