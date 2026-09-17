<x-layouts.public title="Shooting matches in South Africa">
    <main id="main">
        <section class="hero" style="padding:0">
            {{--
                UX audit — cool factor: live radar sweep. The reticle
                SVG stays static; the sweep is a slow-rotating conic-
                gradient in the wrapper's ::after so no per-frame layout
                and no JS. `prefers-reduced-motion` disables the
                animation entirely for motion-sensitive visitors.
            --}}
            <div class="hero-reticle-wrap" aria-hidden="true">
                <svg class="hero-reticle" viewBox="0 0 400 400">
                    <circle cx="200" cy="200" r="182" fill="none" stroke="#D9AE52" stroke-width="1.5"/>
                    <circle cx="200" cy="200" r="120" fill="none" stroke="#D9AE52" stroke-width="1"/>
                    <circle cx="200" cy="200" r="52" fill="none" stroke="#D9AE52" stroke-width="1"/>
                    <path d="M200 0v150M200 250v150M0 200h150M250 200h150" stroke="#D9AE52" stroke-width="1.5"/>
                    <path d="M182 236h36M186 258h28M190 280h20M182 164h36M186 142h28M190 120h20" stroke="#D9AE52" stroke-width="1.5"/>
                    <path d="M164 182v36M142 186v28M120 190v20M236 182v36M258 186v28M280 190v20" stroke="#D9AE52" stroke-width="1.5"/>
                    {{-- UX audit cool-factor: this centre dot is the
                         only thing that moves. See .hero-reticle-dot
                         in app.css for the pulse rule (rotating sweep
                         was ripped out — read as radar). --}}
                    <circle class="hero-reticle-dot" cx="200" cy="200" r="3.5" fill="#D9AE52"/>
                </svg>
            </div>
            <div class="hero-in">
                <div>
                    <p class="label">The national register of South African shooting sport</p>
                    <h1>Find your <em>sport</em>. Find your <em>club</em>. Find your <em>match</em>.</h1>
                    <p class="lede">Every discipline, every province, one calendar. Free to list, free to browse, no account needed to look around.</p>
                    <p class="hero-weekend">
                        <a class="btn" href="{{ route('calendar', ['weekend' => 1]) }}">What's shooting this weekend?</a>
                    </p>
                    <livewire:match-finder />
                    <div class="quick-actions" role="group" aria-label="Quick match filters">
                        <a href="{{ route('calendar', ['weekend' => 1]) }}">This weekend</a>
                        <a href="{{ route('calendar') }}" data-near-me>Near me</a>
                        <a href="{{ route('calendar', ['province' => 'gauteng']) }}">Gauteng</a>
                        <a href="{{ route('calendar', ['novice' => 1]) }}">New shooter friendly</a>
                    </div>
                </div>
                <div class="hero-rail">
                    <div class="rail-head">
                        <h3>{{ $railLabel }}</h3>
                        <span class="label">Nationwide</span>
                    </div>
                    @forelse ($rail as $event)
                        <a class="rail-item" href="{{ route('matches.show', $event->slug) }}">
                            <div class="rail-date">
                                <span class="dow">{{ \App\Support\EventDate::weekday($event->starts_at) }}</span>
                                <b>{{ \App\Support\EventDate::dayOfMonth($event->starts_at) }}</b>
                                <span class="mo">{{ \App\Support\EventDate::monthWithYear($event->starts_at) }}</span>
                            </div>
                            <div class="rail-body">
                                <div class="t">{{ $event->title }}</div>
                                <div class="m">{{ $event->locationLabel() }} · {{ $event->primaryDiscipline()?->name ?? $event->disciplines->first()?->name }}</div>
                            </div>
                        </a>
                    @empty
                        <p class="empty">No upcoming matches listed yet.</p>
                    @endforelse
                    <p class="rail-more">
                        <a href="{{ route('calendar', ['weekend' => 1]) }}">This weekend →</a>
                    </p>
                </div>
            </div>
        </section>

        <div class="strip">
            <div class="strip-in">
                {{-- Bundle A #4: traction stats only. Drop Provinces
                     (geography, not traction) and Industry (gated until
                     seeded). Clubs & series combine membership clubs
                     with branded series so the number matches the
                     directory the visitor actually opens. --}}
                <div><span>Matches</span><b>{{ $stats['matches'] }}</b></div>
                <div><span>Ranges</span><b>{{ $stats['ranges'] }}</b></div>
                <div><span>Disciplines</span><b>{{ $stats['disciplines'] }}</b></div>
                <div><span>Clubs &amp; series</span><b>{{ $stats['clubs'] + $stats['series'] }}</b></div>
            </div>
        </div>

        <section class="block match-board is-teaser" id="calendar" style="padding-top:56px">
            <div class="wrap">
                <div class="sec-head">
                    <p class="label">01 — Matches</p>
                    <h2>What's on, and where</h2>
                    <p>Every match from every club, in one place. Free to list, free to browse, no account needed.</p>
                </div>
                <livewire:calendar-filter :limit="6" :show-more="true" />
            </div>
        </section>

        {{-- Ad rail lives between the first content section and the
             discipline grid, and only renders when there is a live
             placement. A vacant "Advertise here" block above the first
             match reads as thin on a young directory. --}}
        <div class="wrap" style="padding-top:8px; padding-bottom:8px">
            <x-ad-slot page="home" placement-slot="leaderboard" :limit="2" hide-when-vacant />
        </div>

        <section class="block" id="disciplines" style="padding-top:0">
            <div class="wrap">
                <div class="sec-head">
                    <p class="label">02 — Sports</p>
                    <h2>Know the game before you arrive</h2>
                    <p>Each sport is a real page — what it is, who governs it, and where the next match is.</p>
                </div>
                {{-- Bundle A #2: lead with populated tiles; empty ones
                     sit behind a "Show all" toggle so Discover doesn't
                     open as a wall of italic apologies. --}}
                @php
                    $populated = $disciplines->filter(fn ($d) => ($d->events_count ?? 0) > 0);
                    $empty = $disciplines->filter(fn ($d) => ($d->events_count ?? 0) === 0);
                    $total = $disciplines->count();
                @endphp
                <div class="disc-grid" id="disc-populated">
                    @foreach ($populated as $discipline)
                        <a class="disc" href="{{ route('disciplines.show', $discipline->slug) }}">
                            <span class="fam">{{ $discipline->family->getLabel() }}</span>
                            <span class="nm">{{ $discipline->name }}</span>
                            <span class="ct">{{ $discipline->events_count }} upcoming</span>
                        </a>
                    @endforeach
                </div>
                @if ($empty->isNotEmpty())
                    <details class="disc-more">
                        <summary>Show all {{ $total }} disciplines →</summary>
                        <div class="disc-grid" style="margin-top:1px">
                            @foreach ($empty as $discipline)
                                <a class="disc is-quiet" href="{{ route('disciplines.show', $discipline->slug) }}">
                                    <span class="fam">{{ $discipline->family->getLabel() }}</span>
                                    <span class="nm">{{ $discipline->name }}</span>
                                    <span class="ct ct-quiet">No matches listed yet</span>
                                </a>
                            @endforeach
                        </div>
                    </details>
                @endif
            </div>
        </section>

        <section class="block" id="directory" style="padding-top:0">
            <div class="wrap">
                <div class="sec-head">
                    <p class="label">03 — Directory</p>
                    <h2>Clubs &amp; series, ranges, industry</h2>
                    <p>A club is not bound to a venue. Ranges live on the match. Series are branded, recurring matches with no membership. Listings stay free.</p>
                </div>
                <div class="dir-grid">
                    <div class="dir-col">
                        <h3>Clubs &amp; series</h3>
                        <span class="label">Membership clubs, associations, and branded match series</span>
                        <ul>
                            @foreach ($clubs as $club)
                                <li>
                                    <a href="{{ route('clubs.show', $club->slug) }}">{{ $club->name }}</a>
                                    <span>{{ $club->province?->code() }}</span>
                                </li>
                            @endforeach
                        </ul>
                        <a class="dir-more" href="{{ route('clubs.index') }}">All clubs &amp; series →</a>
                    </div>
                    <div class="dir-col">
                        <h3>Ranges</h3>
                        <span class="label">Where the match is shot</span>
                        <ul>
                            @foreach ($ranges as $range)
                                <li>
                                    <a href="{{ route('ranges.show', $range->slug) }}">{{ $range->name }}</a>
                                    <span>{{ $range->province?->code() }}</span>
                                </li>
                            @endforeach
                        </ul>
                        <a class="dir-more" href="{{ route('ranges.index') }}">All ranges →</a>
                    </div>
                    {{-- UX audit #12: hide the whole Industry directory
                         column until we have enough real suppliers to
                         justify a section. An empty grid on the homepage
                         is a worse ad for the ad product than no grid. --}}
                    @if (\App\Models\Provider::isDirectoryPopulated())
                        <div class="dir-col">
                            <h3>Industry</h3>
                            <span class="label">Gunsmiths, dealers, ammunition, optics</span>
                            <ul>
                                @foreach ($suppliers as $supplier)
                                    <li>
                                        <a href="{{ route('suppliers.show', $supplier->slug) }}">{{ $supplier->name }}</a>
                                        <span>{{ $supplier->category->getLabel() }}</span>
                                    </li>
                                @endforeach
                            </ul>
                            <a class="dir-more" href="{{ route('suppliers.index') }}">See the industry →</a>
                        </div>
                    @endif
                </div>
            </div>
        </section>

        <section class="block" id="clubs" style="padding-top:0">
            <div class="wrap">
                <div class="sec-head">
                    <p class="label">04 — For clubs</p>
                    <h2>Listing is free. Forever.</h2>
                    <p>Revenue is advertising, not entry fees. A club page is a public record, not a shopfront we take a cut from.</p>
                </div>
                <div class="steps">
                    <div class="step">
                        <div class="step-n">01</div>
                        <div>
                            <h3>Claim the listing</h3>
                            <p>Staff seed the obvious clubs. You claim yours, confirm the details, and keep them fresh.</p>
                        </div>
                    </div>
                    <div class="step">
                        <div class="step-n">02</div>
                        <div>
                            <h3>Put matches on the calendar</h3>
                            <p>Planned dates are allowed. Confirmed dates look different. Provisional is not the same as locked in.</p>
                        </div>
                    </div>
                    <div class="step">
                        <div class="step-n">03</div>
                        <div>
                            <h3>Stay verified</h3>
                            <p>Freshness is the product. Stale listings fade in search. They are never deleted and never 404.</p>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <section class="cta-band">
            <div class="cta-in">
                <div>
                    <p class="label">Independent · neutral · free to list</p>
                    <h2>The register only works if the clubs are on it.</h2>
                    <p>If your club is missing, claim it from the desk. If a match is missing, a director can add it.</p>
                </div>
                <a class="btn" href="{{ route('login') }}">Director login</a>
            </div>
        </section>
    </main>
</x-layouts.public>
