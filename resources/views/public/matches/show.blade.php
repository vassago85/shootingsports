<x-layouts.public
    :title="$event->title"
    :description="$event->hostDisplayName().' · '.$event->locationLabel().' · '.\App\Support\EventDate::fact($event->starts_at, $event->ends_at)"
    :json-ld="$jsonLd"
>
    @php
        $host = $event->hostOrganisation;
        $venue = $event->venue;
        $hostUrl = $host
            ? ($host->isFederationListing()
                ? route('federations.show', $host->slug)
                : route('clubs.show', $host->slug))
            : null;
        $rangeUrl = $venue ? route('ranges.show', $venue->slug) : null;
        // Multi-venue: `allVenues()` returns the pivot rows when set,
        // otherwise falls back to the single `venue_id` — same list
        // whether the match is at one range or three.
        $allVenues = $event->allVenues();
        $isMultiVenue = $allVenues->count() > 1;
    @endphp
    <main id="main">
        <section class="page-hero">
            <div class="wrap">
                <p class="label">{{ \App\Support\EventDate::headline($event->starts_at, $event->ends_at) }}</p>
                <h1>{{ $event->title }}</h1>
                <p>
                    @if ($hostUrl)
                        <a href="{{ $hostUrl }}">{{ $event->hostDisplayName() }}</a>
                    @else
                        {{ $event->hostDisplayName() }}
                    @endif
                    @if ($rangeUrl)
                        · <a href="{{ $rangeUrl }}">{{ $event->locationLabel() }}</a>
                    @elseif ($event->locationLabel() !== 'Venue TBC')
                        · {{ $event->locationLabel() }}
                    @endif
                </p>
                <div class="pill-row" style="position:static;margin-top:16px">
                    <x-event-status-pill :event="$event" />
                </div>
            </div>
        </section>
        <section class="block">
            <div class="wrap">
                {{-- Bundle A #6: facts-first two-column layout.
                     Poster capped left; sticky fact card right with
                     the primary Enter here CTA. --}}
                <div class="match-layout">
                    <div class="match-layout-media">
                        @if ($bannerUrl = $event->bannerUrl())
                            <figure class="match-poster">
                                <img src="{{ $bannerUrl }}" alt="{{ $event->title }} match poster">
                            </figure>
                        @endif
                        @if ($event->description)
                            <div class="prose">{!! nl2br(e($event->description)) !!}</div>
                        @endif
                    </div>

                    <aside class="match-facts">
                        <dl class="dope-rows">
                            <div class="r">
                                <dt>Date</dt>
                                <dd>{{ \App\Support\EventDate::fact($event->starts_at, $event->ends_at) }}</dd>
                            </div>
                            @foreach ($specs as [$label, $value])
                                @if ($label === 'Venue' && $isMultiVenue)
                                    {{-- Multi-venue matches list every range on its own row,
                                         each with a day label and directions link. --}}
                                    <div class="r">
                                        <dt>{{ $allVenues->count() }} venues</dt>
                                        <dd>
                                            <ul style="list-style:none;margin:0;padding:0;display:grid;gap:6px">
                                                @foreach ($allVenues as $ev)
                                                    <li>
                                                        @if ($ev->pivot?->day_label)
                                                            <b>{{ $ev->pivot->day_label }}:</b>
                                                        @endif
                                                        <a href="{{ route('ranges.show', $ev->slug) }}">{{ $ev->name }}</a>
                                                        · <a href="{{ $ev->directionsUrl() }}" rel="noopener noreferrer" target="_blank">Directions</a>
                                                    </li>
                                                @endforeach
                                            </ul>
                                        </dd>
                                    </div>
                                @elseif ($label === 'Venue' && $rangeUrl)
                                    <div class="r">
                                        <dt>Venue</dt>
                                        <dd>
                                            <a href="{{ $rangeUrl }}">{{ $value }}</a>
                                            @if ($venue)
                                                · <a href="{{ $venue->directionsUrl() }}" rel="noopener noreferrer" target="_blank">Directions</a>
                                            @endif
                                        </dd>
                                    </div>
                                @else
                                    <div class="r"><dt>{{ $label }}</dt><dd>{{ $value }}</dd></div>
                                @endif
                            @endforeach
                            @if ($event->level)
                                <div class="r"><dt>Level</dt><dd>{{ $event->level->getLabel() }}</dd></div>
                            @endif
                        </dl>

                        <div class="match-entry-cta">
                            @if ($event->entry_url)
                                <a
                                    class="btn"
                                    href="{{ $event->entry_url }}"
                                    rel="noopener noreferrer"
                                    target="_blank"
                                >Enter here →</a>
                                <p class="match-entry-note">
                                    Opens {{ parse_url($event->entry_url, PHP_URL_HOST) ?: 'the host\'s entry page' }} in a new tab.
                                    We list third-party events — we are not the organiser.
                                    See <a href="{{ route('terms') }}">Terms of Use</a>.
                                </p>
                            @else
                                <p class="match-entry-note">
                                    No online entry link on file for this match.
                                    @if ($hostUrl)
                                        Contact <a href="{{ $hostUrl }}">{{ $host->name }}</a> to enter.
                                    @else
                                        Ask at the range on the day.
                                    @endif
                                    We list third-party events — we are not the organiser.
                                    See <a href="{{ route('terms') }}">Terms of Use</a>.
                                </p>
                            @endif
                        </div>

                        <div class="match-secondary">
                            @auth
                                <livewire:save-to-calendar :event="$event" variant="button" :key="'save-match-'.$event->id" />
                                <livewire:log-attendance :event="$event" :key="'log-match-'.$event->id" />
                            @else
                                <a class="btn ghost" href="{{ route('login') }}">Add to my calendar</a>
                                <a class="btn ghost" href="{{ route('login') }}">I shot this</a>
                            @endauth
                        </div>

                        <p class="match-named-links">
                            @if ($hostUrl)
                                <a href="{{ $hostUrl }}">{{ $host->name }}</a>
                            @endif
                            @if ($rangeUrl)
                                @if ($hostUrl) · @endif
                                <a href="{{ $rangeUrl }}">{{ $venue->name }}</a>
                            @endif
                        </p>
                    </aside>
                </div>
            </div>
        </section>
    </main>
</x-layouts.public>
