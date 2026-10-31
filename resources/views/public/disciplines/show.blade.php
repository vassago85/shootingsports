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
                <h1>{{ $seo->title }}</h1>
                <p class="lede">{{ $answer }}</p>
                @if ($introHtml)
                    <div class="discipline-intro">{!! $introHtml !!}</div>
                @endif
                @if (filled($discipline->short_blurb))
                    <p>{{ $discipline->short_blurb }}</p>
                @endif
                <p style="margin-top:18px">
                    <a class="sport-link" href="{{ route('disciplines.about', $discipline->slug) }}">More about this sport →</a>
                    <a class="sport-link" href="{{ route('disciplines.rankings', $discipline) }}">Rankings →</a>
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
            <section id="matches" class="block">
                <div class="sec-head">
                    <p class="label">Calendar</p>
                    <h2>Upcoming matches</h2>
                </div>
                @forelse ($events->groupBy(fn ($event) => $event->starts_at->timezone(config('app.timezone'))->format('Y-m')) as $monthEvents)
                    <h3>{{ $monthEvents->first()->starts_at->timezone(config('app.timezone'))->format('F Y') }}</h3>
                    @foreach ($monthEvents as $event)
                        <a class="match-row" href="{{ route('events.show', $event->slug) }}">
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
                    @endforeach
                @empty
                    <p class="empty">No upcoming {{ $discipline->name }} match is listed{{ $province ? ' in '.$province->getLabel() : ' in South Africa' }}.</p>
                    @if ($nationalUpcoming->isNotEmpty())
                        <h3>Next matches nationally</h3>
                        @foreach ($nationalUpcoming as $event)
                            <p><a href="{{ route('events.show', $event->slug) }}">{{ $event->title }}</a></p>
                        @endforeach
                        <p><a class="sport-link" href="{{ route('disciplines.show', $discipline->slug) }}">All {{ $discipline->name }} matches in South Africa</a></p>
                    @endif
                @endforelse
            </section>

            <section id="provinces" class="block">
                <div class="sec-head">
                    <p class="label">Map</p>
                    <h2>Where to shoot by province</h2>
                </div>
                @if ($provinceDirectory === [])
                    <p class="empty">No club or range lists {{ $discipline->name }} yet.</p>
                @else
                    <ul>
                        @foreach ($provinceDirectory as $row)
                            <li>
                                <a href="{{ $row['url'] }}">{{ $row['label'] }}</a>
                                — {{ $row['clubs'] }} {{ \Illuminate\Support\Str::plural('club', $row['clubs']) }}, {{ $row['ranges'] }} {{ \Illuminate\Support\Str::plural('range', $row['ranges']) }}
                            </li>
                        @endforeach
                    </ul>
                @endif
            </section>

            @if (($clubs ?? collect())->isNotEmpty())
                <section id="clubs" class="block">
                    <div class="sec-head">
                        <p class="label">Where to shoot</p>
                        <h2>Clubs that run this discipline</h2>
                    </div>
                    <div class="dope-grid">
                        @foreach ($clubs as $club)
                            <a class="listing" href="{{ route('clubs.show', $club->slug) }}">
                                <h3>{{ $club->name }}</h3>
                                @if ($club->province)
                                    <p class="meta">{{ $club->province->getLabel() }}</p>
                                @endif
                            </a>
                        @endforeach
                    </div>
                </section>
            @endif

            @if (($ranges ?? collect())->isNotEmpty())
                <section id="ranges" class="block">
                    <div class="sec-head">
                        <p class="label">Facilities</p>
                        <h2>Ranges that host it</h2>
                    </div>
                    <div class="dope-grid">
                        @foreach ($ranges as $range)
                            <a class="listing" href="{{ route('ranges.show', $range->slug) }}">
                                <h3>{{ $range->name }}</h3>
                                @if ($range->province)
                                    <p class="meta">{{ $range->province->getLabel() }}</p>
                                @endif
                            </a>
                        @endforeach
                    </div>
                </section>
            @endif

            <section id="results" class="block">
                <div class="sec-head">
                    <p class="label">Scoreboard</p>
                    <h2>Recent results</h2>
                </div>
                @forelse ($recentResults as $event)
                    <p><a href="{{ route('events.show', $event->slug) }}">{{ $event->title }}</a></p>
                @empty
                    <p class="empty">No completed {{ $discipline->name }} match is listed{{ $province ? ' in '.$province->getLabel() : '' }}.</p>
                @endforelse
            </section>

            <section id="governing-body" class="block">
                <div class="sec-head">
                    <p class="label">Authority</p>
                    <h2>Governing body and links</h2>
                </div>
                @if ($discipline->federation)
                    <p>
                        @if ($discipline->federation->status === \App\Enums\ListingStatus::Published)
                            <a href="{{ route('federations.show', $discipline->federation->slug) }}">{{ $discipline->federation->name }}</a>
                        @else
                            {{ $discipline->federation->name }}
                        @endif
                    </p>
                    @if (filled($discipline->federation->website_url))
                        <p><a href="{{ $discipline->federation->website_url }}" rel="noopener noreferrer">{{ $discipline->federation->website_url }}</a></p>
                    @endif
                @endif
                <p>
                    <a class="sport-link" href="{{ route('calendar', ['discipline' => $discipline->slug, 'province' => $province?->urlSlug()]) }}">Open in the calendar</a>
                    <a class="sport-link" href="{{ route('ical.discipline', $discipline->slug) }}">iCal feed</a>
                    <a class="sport-link" href="{{ route('disciplines.about', $discipline->slug) }}">More information</a>
                </p>
            </section>

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

            <section class="block" style="padding-top:0">
                @if (($articles ?? collect())->isNotEmpty())
                    <h2 style="margin-top:28px">Activity</h2>
                    <ul>
                        @foreach ($articles as $article)
                            <li><a href="{{ route('feed') }}">{{ $article->title }}</a></li>
                        @endforeach
                    </ul>
                @endif
            </section>
        </div>
    </main>
</x-layouts.public>
