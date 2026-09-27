<x-layouts.public
    :title="$seo->title"
    :description="$seo->description"
    :canonical="$seo->canonical"
    :robots="$seo->robots"
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
                @php
                    $hostName = $event->listedHost();
                    $place = $event->listedLocation();
                @endphp
                @if ($hostName || $place)
                    <p>
                        @if ($hostName)
                            @if ($hostUrl)
                                <a href="{{ $hostUrl }}">{{ $hostName }}</a>
                            @else
                                {{ $hostName }}
                            @endif
                        @endif
                        @if ($place)
                            @if ($hostName) · @endif
                            @if ($rangeUrl)
                                <a href="{{ $rangeUrl }}">{{ $place }}</a>
                            @else
                                {{ $place }}
                            @endif
                        @endif
                    </p>
                @endif
                <div class="pill-row" style="position:static;margin-top:16px">
                    <x-event-status-pill :event="$event" />
                </div>
            </div>
        </section>
        <div class="wrap" style="padding-top:28px">
            @foreach ($event->disciplines->flatMap(fn ($sport) => $sport->divisions())->unique(fn ($division) => $division->value)->take(3) as $sponsorDivision)
                <x-ad-slot page="disciplines" placement-slot="category_sponsor" :division="$sponsorDivision" :limit="1" hide-when-vacant />
            @endforeach
            <x-ad-slot page="disciplines" placement-slot="category_sponsor" :disciplines="$event->disciplines" :limit="1" hide-when-vacant />
        </div>
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

                        @if ($event->partners->isNotEmpty())
                            <div class="match-partners">
                                <h2>Partners</h2>
                                <ul>
                                    @foreach ($event->partners as $partner)
                                        <li>
                                            <a href="{{ route('suppliers.show', $partner) }}">
                                                @if ($partner->logoUrl())
                                                    <img src="{{ $partner->logoUrl() }}" alt="">
                                                @else
                                                    <span class="match-partner-mark">{{ $partner->initials() }}</span>
                                                @endif
                                                <span>{{ $partner->name }}</span>
                                            </a>
                                        </li>
                                    @endforeach
                                </ul>
                            </div>
                        @endif

                        @php
                            $isPast = $event->hasPassed();
                            $hasExternalEntry = filled($event->entry_url);
                            $hasPlatformEntry = $event->openForPlatformEntries();
                        @endphp

                        {{-- Before the match, Enter is the primary action.
                             After the date, we stop shouting "Enter here"
                             so the personal log takes the lead below. --}}
                        @if (! $isPast && $hasExternalEntry)
                            <div class="match-entry-cta">
                                <a
                                    class="btn"
                                    href="{{ $event->entry_url }}"
                                    rel="noopener noreferrer"
                                    target="_blank"
                                >Enter here →</a>
                                <p class="match-entry-note">
                                    Opens {{ parse_url($event->entry_url, PHP_URL_HOST) ?: 'the host\'s entry page' }} in a new tab.
                                    We list third-party events. We are not the organiser.
                                    See <a href="{{ route('terms') }}">Terms of Use</a>.
                                </p>
                            </div>
                        @endif

                        @if (! $isPast && $hasPlatformEntry)
                            <livewire:enter-event :event="$event" :key="'enter-'.$event->id" />
                        @endif

                        {{-- Secondary actions. Save + I shot this + Share.
                             After the match, the personal log promotes. --}}
                        <div class="match-secondary">
                            @auth
                                <livewire:save-to-calendar :event="$event" variant="button" :key="'save-match-'.$event->id" />
                                <livewire:log-attendance :event="$event" :key="'log-match-'.$event->id" />
                            @else
                                <x-auth-gate action="save" :label="$isPast ? 'Add to my calendar' : 'Add to my calendar'" />
                                <x-auth-gate action="log" label="I shot this" />
                            @endauth
                            <button
                                type="button"
                                class="btn ghost"
                                data-share
                                data-share-title="{{ $event->title }}"
                                data-share-url="{{ route('events.show', $event->slug) }}"
                            >Share</button>
                        </div>

                        @if ($isPast && $hasExternalEntry)
                            <p class="match-entry-note" style="margin-top:12px">
                                Entries closed. External entry page was
                                <a href="{{ $event->entry_url }}" rel="noopener noreferrer" target="_blank">{{ parse_url($event->entry_url, PHP_URL_HOST) ?: 'the host page' }}</a>.
                            </p>
                        @endif

                        <p class="match-entry-note" style="margin-top:14px">
                            <a href="{{ route('enquiries.listing', ['type' => 'event', 'id' => $event->id]) }}">Ask the organiser</a>
                        </p>

                        @if ($articles->isNotEmpty())
                            <div class="match-partners">
                                <h2>Activity</h2>
                                <ul>
                                    @foreach ($articles as $article)
                                        <li><a href="{{ route('feed', ['discipline' => $article->disciplines->first()?->slug]) }}">{{ $article->title }}</a></li>
                                    @endforeach
                                </ul>
                            </div>
                        @endif

                        @if ($hostUrl || $rangeUrl)
                            <p class="match-named-links">
                                @if ($hostUrl)
                                    <a href="{{ $hostUrl }}">{{ $host->name }}</a>
                                @endif
                                @if ($rangeUrl)
                                    @if ($hostUrl) · @endif
                                    <a href="{{ $rangeUrl }}">{{ $venue->name }}</a>
                                @endif
                            </p>
                        @endif
                    </aside>
                </div>

                @if (($relatedEvents ?? collect())->isNotEmpty())
                    {{-- Same-discipline upcoming matches. Uses the
                         shared calendar card so pills, dates and
                         status match the rest of the site. --}}
                    <section class="match-related">
                        <div class="sec-head">
                            <p class="label">Also on the calendar</p>
                            <h2>Other {{ $event->primaryDiscipline()?->name ?? 'matches' }} coming up</h2>
                        </div>
                        <div class="dope-grid">
                            @foreach ($relatedEvents as $related)
                                <x-event-card :event="$related" />
                            @endforeach
                        </div>
                    </section>
                @endif

                {{-- Owner / admin panel — sits at the bottom so shooter
                     actions above stay uncluttered. Only rendered when
                     something is genuinely unclaimed. --}}
                @if (($host && $host->claimed_by === null) || ($venue && $venue->claimed_by === null))
                    <x-owner-panel title="For match directors & range operators">
                        <p>Recognise this match or venue? Claim it and unlock director tools.</p>
                        <div class="owner-actions">
                            @if ($host && $host->claimed_by === null)
                                <a class="btn ghost" href="{{ route('listings.claim', ['type' => 'match', 'slug' => $event->slug]) }}">This is my match</a>
                            @endif
                            @if ($venue && $venue->claimed_by === null)
                                <a class="btn ghost" href="{{ route('listings.claim', ['type' => 'range', 'slug' => $venue->slug]) }}">This is my range</a>
                            @endif
                        </div>
                    </x-owner-panel>
                @endif
            </div>
        </section>
    </main>

    <script>
        document.addEventListener('click', (event) => {
            const button = event.target.closest('[data-share]');
            if (! button) { return; }
            const url = button.getAttribute('data-share-url') || window.location.href;
            const title = button.getAttribute('data-share-title') || document.title;
            if (navigator.share) {
                navigator.share({ title, url }).catch(() => {});
                return;
            }
            if (navigator.clipboard) {
                navigator.clipboard.writeText(url).then(() => {
                    const original = button.textContent;
                    button.textContent = 'Link copied';
                    setTimeout(() => { button.textContent = original; }, 2000);
                }).catch(() => window.prompt('Copy link', url));
                return;
            }
            window.prompt('Copy link', url);
        });
    </script>
</x-layouts.public>
