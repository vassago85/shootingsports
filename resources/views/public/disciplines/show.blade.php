<x-layouts.public
    :title="$province ? $discipline->name.' in '.$province->getLabel() : $discipline->name"
    :description="$discipline->short_blurb"
    :robots="$noindex ? 'noindex,follow' : null"
>
    <main id="main">
        <section class="dhero" data-family="{{ $discipline->family->value }}">
            <svg class="reticle" viewBox="0 0 400 400" aria-hidden="true">
                <circle cx="200" cy="200" r="182" fill="none" stroke="#C8D8AC" stroke-width="1.5"/>
                <circle cx="200" cy="200" r="120" fill="none" stroke="#C8D8AC" stroke-width="1"/>
                <circle cx="200" cy="200" r="52" fill="none" stroke="#C8D8AC" stroke-width="1"/>
                <path d="M200 0v150M200 250v150M0 200h150M250 200h150" stroke="#C8D8AC" stroke-width="1.5"/>
                <circle cx="200" cy="200" r="3.5" fill="#C8D8AC"/>
            </svg>
            <div class="dhero-in">
                <p class="crumb">
                    <a href="{{ route('disciplines.index') }}">Disciplines</a>
                    &nbsp;/&nbsp; {{ $discipline->family->getLabel() }}
                    @if ($province)
                        &nbsp;/&nbsp; {{ $province->getLabel() }}
                    @endif
                    &nbsp;/&nbsp; <b>{{ $discipline->name }}</b>
                </p>
                <h1>{{ $discipline->name }}@if ($province) <span style="color:#c4cdb6">in {{ $province->getLabel() }}</span>@endif</h1>
                <p class="lede">{{ $discipline->short_blurb }}</p>
                <div class="gov">
                    @if ($discipline->children->isNotEmpty())
                        <div>Formats <b>{{ $discipline->children->pluck('name')->implode(' · ') }}</b></div>
                    @endif
                    @if ($discipline->federation)
                        <div>Governed by <b><a href="{{ route('federations.show', $discipline->federation->slug) }}">{{ $discipline->federation->name }}</a></b></div>
                    @endif
                    @if ($discipline->typical_distances)
                        <div>Typical distance <b>{{ $discipline->typical_distances }}</b></div>
                    @endif
                </div>
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

        <div class="strip">
            <div class="strip-in">
                <div><span>Upcoming matches</span><b>{{ $figures['matches'] }}</b></div>
                <div><span>Clubs</span><b>{{ $figures['clubs'] }}</b></div>
                @if ($figures['distance'])
                    <div><span>Typical distance</span><b>{{ $figures['distance'] }}</b></div>
                @endif
                <div><span>Provinces active</span><b>{{ $figures['provinces'] }}</b></div>
            </div>
        </div>

        <div class="wrap">
            <div class="cols">
                <div class="body">
                    <section id="what">
                        <div class="sec-head">
                            <p class="label">Overview</p>
                            <h2>What {{ strtolower($discipline->name) }} is</h2>
                        </div>
                        <div class="prose">
                            @forelse (preg_split('/\n\s*\n/', (string) $discipline->body) ?: [] as $paragraph)
                                @if (filled(trim($paragraph)))
                                    <p>{{ trim($paragraph) }}</p>
                                @endif
                            @empty
                                <p>{{ $discipline->short_blurb }}</p>
                            @endforelse
                        </div>

                        @if ($discipline->children->isNotEmpty())
                            <div class="formats">
                                @foreach ($discipline->children as $child)
                                    <div class="fmt">
                                        <div class="fmt-top">
                                            <h3>{{ $child->name }}</h3>
                                            <span>{{ $child->short_blurb }}</span>
                                        </div>
                                        <dl>
                                            @if ($child->typical_distances)
                                                <div><dt>Distance</dt><dd>{{ $child->typical_distances }}</dd></div>
                                            @endif
                                            @if ($child->equipment_rules)
                                                <div><dt>Equipment</dt><dd>{{ $child->equipment_rules }}</dd></div>
                                            @endif
                                        </dl>
                                    </div>
                                @endforeach
                            </div>
                        @endif
                    </section>

                    <hr class="rule-off">

                    <section id="matches">
                        <div class="sec-head">
                            <p class="label">Calendar</p>
                            <h2>Next {{ strtolower($discipline->name) }} matches</h2>
                        </div>
                        @forelse ($events as $event)
                            <a class="match-row" href="{{ route('matches.show', $event->slug) }}">
                                <div class="m-date">
                                    <span class="dow">{{ \App\Support\EventDate::weekday($event->starts_at) }}</span>
                                    <b>{{ \App\Support\EventDate::dayOfMonth($event->starts_at) }}</b>
                                    <span class="mo">{{ \App\Support\EventDate::monthWithYear($event->starts_at) }}</span>
                                </div>
                                <div class="m-body">
                                    <div class="t">{{ $event->title }}</div>
                                    <div class="m">{{ $event->hostDisplayName() }} · {{ $event->locationLabel() }}</div>
                                </div>
                                <div class="m-pills">
                                    @if ($format = $event->primaryDiscipline() ?? $event->disciplines->first())
                                        <span class="pill confirmed">{{ $format->name }}</span>
                                    @endif
                                    <x-event-status-pill :event="$event" />
                                </div>
                            </a>
                        @empty
                            <p class="empty">No upcoming matches in this slice yet. Clubs can still list a planned date.</p>
                        @endforelse
                        <p style="margin-top:18px">
                            <a class="label" href="{{ route('calendar', ['discipline' => $discipline->slug, 'province' => $province?->urlSlug()]) }}" style="text-decoration:none;border-bottom:1px solid var(--brass)">Open in the calendar →</a>
                            &nbsp;&nbsp;
                            <a class="label" href="{{ route('ical.discipline', $discipline->slug) }}" style="text-decoration:none;border-bottom:1px solid var(--brass)">iCal feed →</a>
                        </p>
                    </section>

                    <hr class="rule-off">

                    <section id="clubs">
                        <div class="sec-head">
                            <p class="label">Where it is shot</p>
                            <h2>Clubs running {{ strtolower($discipline->name) }}</h2>
                            <p>Clubs are listed by home province. A club is not bound to a venue — the range lives on the match.</p>
                        </div>
                        <div class="clubs">
                            @forelse ($clubs as $club)
                                <article class="club-card">
                                    <div>
                                        <h3><a href="{{ route('clubs.show', $club->slug) }}">{{ $club->name }}</a></h3>
                                        <p class="where">{{ $club->province?->getLabel() }}@if ($club->town) · {{ $club->town }}@endif</p>
                                        <div class="club-tags">
                                            @foreach ($club->disciplines as $clubDisc)
                                                <span class="tag">{{ $clubDisc->name }}</span>
                                            @endforeach
                                            @if ($club->visitors_welcome)
                                                <span class="tag key">Visitors welcome</span>
                                            @endif
                                        </div>
                                    </div>
                                    <div>
                                        <x-verification-badge :listing="$club" />
                                    </div>
                                </article>
                            @empty
                                <p class="empty">No clubs listed for this slice yet.</p>
                            @endforelse
                        </div>
                    </section>

                    <hr class="rule-off">

                    <section id="related">
                        <div class="sec-head">
                            <p class="label">If this interests you</p>
                            <h2>Try these next</h2>
                        </div>
                        <div class="related">
                            @foreach ($related as $item)
                                <a class="rel" href="{{ route('disciplines.show', $item->slug) }}">
                                    <span class="k">{{ $item->family->getLabel() }}</span>
                                    <span class="t">{{ $item->name }}</span>
                                    <span class="d">{{ $item->short_blurb }}</span>
                                </a>
                            @endforeach
                        </div>
                    </section>
                </div>

                <aside>
                    <div class="side-box">
                        <h4>By province</h4>
                        <ul>
                            @foreach (\App\Enums\Province::cases() as $item)
                                <li>
                                    <a href="{{ route('disciplines.province', [$discipline->slug, $item->urlSlug()]) }}">{{ $item->getLabel() }}</a>
                                    <span>{{ $item->code() }}</span>
                                </li>
                            @endforeach
                        </ul>
                    </div>
                </aside>
            </div>
        </div>
    </main>
</x-layouts.public>
