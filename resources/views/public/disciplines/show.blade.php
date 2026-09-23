<x-layouts.public
    :title="$seo->title"
    :description="$seo->description"
    :canonical="$seo->canonical"
    :robots="$seo->robots"
    :json-ld="$jsonLd"
>
    <main id="main" class="sport-page">
        <section @class(['dhero', 'has-photo' => filled($discipline->imageUrl())]) data-family="{{ $discipline->family->value }}" @if ($discipline->imageUrl()) style="--hero-image: url('{{ str_replace(['\\', "'"], ['/', ''], $discipline->imageUrl()) }}')" @endif>
            <div class="dhero-in">
                <p class="crumb">
                    <a href="{{ route('disciplines.index') }}">Disciplines</a>
                    &nbsp;/&nbsp; {{ $discipline->family->getLabel() }}
                    @if ($province)
                        &nbsp;/&nbsp; {{ $province->getLabel() }}
                    @endif
                    &nbsp;/&nbsp; <b>{{ $discipline->name }}</b>
                </p>
                <h1>@if ($province){{ $discipline->name }} Clubs in {{ $province->getLabel() }}@else{{ $discipline->name }}@endif</h1>
                @if (filled($discipline->short_blurb))
                    <p class="lede">{{ $discipline->short_blurb }}</p>
                @endif
                <p style="margin-top:18px">
                    <a class="sport-link" href="{{ route('disciplines.about', $discipline->slug) }}">More about this sport →</a>
                </p>
                <nav class="siblings" aria-label="Related disciplines in this family">
                    @foreach ($familySiblings as $sibling)
                        <a href="{{ route('disciplines.show', $sibling->slug) }}" class="{{ $sibling->is($discipline) || $sibling->is($discipline->parent) ? 'on' : '' }}">{{ $sibling->name }}</a>
                    @endforeach
                </nav>

                <div style="margin-top:18px">
                    <livewire:follow-button
                        type="discipline"
                        :id="$discipline->id"
                        label="Follow this discipline"
                        :key="'follow-disc-'.$discipline->id"
                    />
                </div>
            </div>
        </section>

        @php
            $sportStats = collect([
                ['Upcoming matches', $figures['matches']],
                ['Clubs', $figures['clubs']],
                ['Typical distance', $figures['distance'] ?? null],
                ['Provinces active', $figures['provinces']],
            ])->filter(fn (array $stat): bool => filled($stat[1]) && $stat[1] !== 0);
        @endphp
        @if ($sportStats->isNotEmpty())
            <div class="strip home-stats">
                <div class="strip-in">
                    @foreach ($sportStats as [$label, $value])
                        <div><span>{{ $label }}</span><b>{{ $value }}</b></div>
                    @endforeach
                </div>
            </div>
        @endif

        <div class="wrap ss-partner-wrap">
            @foreach ($sponsorDivisions as $sponsorDivision)
                <x-ad-slot page="disciplines" placement-slot="category_sponsor" :division="$sponsorDivision" :limit="1" hide-when-vacant />
            @endforeach
            <x-ad-slot page="disciplines" placement-slot="category_sponsor" :disciplines="[$discipline]" :limit="1" hide-when-vacant />
        </div>

        <div class="wrap">
            @if ($discipline->videos->isNotEmpty())
                <section id="videos" class="block" style="padding-bottom:0">
                    <div class="sec-head">
                        <p class="label">Watch</p>
                        <h2>Videos</h2>
                    </div>
                    <div class="sport-videos">
                        @foreach ($discipline->videos as $video)
                            <a class="sport-video" href="{{ $video->watchUrl() }}" target="_blank" rel="noopener noreferrer">
                                <img src="{{ $video->thumbnailUrl() }}" alt="">
                                <span>{{ $video->title }} · YouTube</span>
                            </a>
                        @endforeach
                    </div>
                </section>
            @endif

            <section id="matches" class="block">
                <div class="sec-head">
                    <p class="label">Calendar</p>
                    <h2>Next {{ $discipline->name }} matches</h2>
                </div>
                @forelse ($events as $event)
                    <a class="match-row" href="{{ route('matches.show', $event->slug) }}">
                        <div class="m-date">
                            <span class="dow">{{ \App\Support\EventDate::weekday($event->starts_at, $event->ends_at) }}</span>
                            <b>{{ \App\Support\EventDate::dayOfMonth($event->starts_at, $event->ends_at) }}</b>
                            <span class="mo">{{ \App\Support\EventDate::monthWithYear($event->starts_at, $event->ends_at) }}</span>
                        </div>
                        <div class="m-body">
                            <div class="t">{{ $event->title }}</div>
                            @php
                                $matchMeta = collect([$event->listedHost(), $event->listedLocation()])->filter();
                            @endphp
                            @if ($matchMeta->isNotEmpty())
                                <div class="m">{{ $matchMeta->implode(' · ') }}</div>
                            @endif
                        </div>
                        <div class="m-pills">
                            @if ($format = $event->primaryDiscipline() ?? $event->disciplines->first())
                                <span class="pill confirmed">{{ $format->name }}</span>
                            @endif
                            <x-event-status-pill :event="$event" />
                        </div>
                    </a>
                @empty
                    <p class="empty">
                        Nobody has listed a {{ $discipline->name }} match yet.
                        Follow this discipline and we’ll surface it when one lands.
                    </p>
                @endforelse
                <p style="margin-top:18px">
                    <a class="sport-link" href="{{ route('calendar', ['discipline' => $discipline->slug, 'province' => $province?->urlSlug()]) }}">Open in the calendar →</a>
                    <a class="sport-link" href="{{ route('ical.discipline', $discipline->slug) }}">iCal feed →</a>
                </p>
            </section>
        </div>
    </main>
</x-layouts.public>
