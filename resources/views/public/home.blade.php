<x-layouts.public title="Find your next shoot">
    <main id="main">
        <section class="hero" style="padding:0">
            <svg class="hero-reticle" viewBox="0 0 400 400" aria-hidden="true">
                <circle cx="200" cy="200" r="182" fill="none" stroke="#D9AE52" stroke-width="1.5"/>
                <circle cx="200" cy="200" r="120" fill="none" stroke="#D9AE52" stroke-width="1"/>
                <circle cx="200" cy="200" r="52" fill="none" stroke="#D9AE52" stroke-width="1"/>
                <path d="M200 0v150M200 250v150M0 200h150M250 200h150" stroke="#D9AE52" stroke-width="1.5"/>
                <path d="M182 236h36M186 258h28M190 280h20M182 164h36M186 142h28M190 120h20" stroke="#D9AE52" stroke-width="1.5"/>
                <path d="M164 182v36M142 186v28M120 190v20M236 182v36M258 186v28M280 190v20" stroke="#D9AE52" stroke-width="1.5"/>
                <circle cx="200" cy="200" r="3.5" fill="#D9AE52"/>
            </svg>
            <div class="hero-in">
                <div>
                    <p class="label">Every discipline · every province · one calendar</p>
                    <h1>Find your next <em>shoot</em>.</h1>
                    <p class="lede">The national register of South African shooting sport. Clubs, ranges, suppliers and every match on the calendar — filtered by discipline, by province, and by how far you are willing to drive.</p>
                    <livewire:match-finder />
                </div>
                <div>
                    <div class="rail-head">
                        <h3>{{ $railLabel }}</h3>
                        <span class="label">Nationwide</span>
                    </div>
                    @forelse ($rail as $event)
                        <a class="rail-item" href="{{ route('matches.show', $event->slug) }}">
                            <div class="rail-date">
                                <b>{{ $event->starts_at->timezone('Africa/Johannesburg')->format('j') }}</b>
                                {{ $event->starts_at->timezone('Africa/Johannesburg')->format('M') }}
                            </div>
                            <div class="rail-body">
                                <div class="t">{{ $event->title }}</div>
                                <div class="m">{{ $event->locationLabel() }} · {{ $event->primaryDiscipline()?->name ?? $event->disciplines->first()?->name }}</div>
                            </div>
                        </a>
                    @empty
                        <p class="empty">No upcoming matches listed yet.</p>
                    @endforelse
                </div>
            </div>
        </section>

        <div class="strip">
            <div class="strip-in">
                <div><span>Clubs</span><b>{{ $stats['clubs'] }}</b></div>
                <div><span>Ranges</span><b>{{ $stats['ranges'] }}</b></div>
                <div><span>Matches listed</span><b>{{ $stats['matches'] }}</b></div>
                <div><span>Disciplines</span><b>{{ $stats['disciplines'] }}</b></div>
                <div><span>Provinces</span><b>{{ $stats['provinces'] }}</b></div>
                <div><span>Suppliers</span><b>{{ $stats['suppliers'] }}</b></div>
            </div>
        </div>

        <section class="block" id="calendar">
            <div class="wrap">
                <div class="sec-head">
                    <p class="label">01 — The calendar</p>
                    <h2>What's on, and where</h2>
                    <p>Every match from every club, in one place. Free to list, free to browse, no account needed.</p>
                </div>
                <livewire:calendar-filter :limit="6" :show-more="true" />
            </div>
        </section>

        <section class="block" id="disciplines" style="padding-top:0">
            <div class="wrap">
                <div class="sec-head">
                    <p class="label">02 — Disciplines</p>
                    <h2>Know the game before you arrive</h2>
                    <p>Each discipline is a real page — what it is, who governs it, and where the next match is.</p>
                </div>
                <div class="disc-grid">
                    @foreach ($disciplines as $discipline)
                        <a class="disc" href="{{ route('disciplines.show', $discipline->slug) }}">
                            <span class="fam">{{ $discipline->family->getLabel() }}</span>
                            <span class="nm">{{ $discipline->name }}</span>
                            <span class="ct">{{ $discipline->events_count }} upcoming</span>
                        </a>
                    @endforeach
                </div>
            </div>
        </section>

        <section class="block" id="directory" style="padding-top:0">
            <div class="wrap">
                <div class="sec-head">
                    <p class="label">03 — Directory</p>
                    <h2>Clubs, ranges, suppliers</h2>
                    <p>A club is not bound to a venue. Ranges live on the match. Listings stay free.</p>
                </div>
                <div class="dir-grid">
                    <div class="dir-col">
                        <h3>Clubs</h3>
                        <span class="label">By home province</span>
                        <ul>
                            @foreach ($clubs as $club)
                                <li>
                                    <a href="{{ route('clubs.show', $club->slug) }}">{{ $club->name }}</a>
                                    <span>{{ $club->province?->code() }}</span>
                                </li>
                            @endforeach
                        </ul>
                        <a class="dir-more" href="{{ route('clubs.index') }}">All clubs →</a>
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
                    <div class="dir-col">
                        <h3>Suppliers</h3>
                        <span class="label">Gunsmiths, dealers, instructors</span>
                        <ul>
                            @foreach ($suppliers as $supplier)
                                <li>
                                    <a href="{{ route('suppliers.show', $supplier->slug) }}">{{ $supplier->name }}</a>
                                    <span>{{ $supplier->category->getLabel() }}</span>
                                </li>
                            @endforeach
                        </ul>
                        <a class="dir-more" href="{{ route('suppliers.index') }}">All suppliers →</a>
                    </div>
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
                    <p>If your club is missing, claim it. If a match is missing, send it in. The calendar is only as good as the last confirmed date.</p>
                </div>
                <a class="btn" href="{{ route('claim') }}">Claim a listing</a>
            </div>
        </section>
    </main>
</x-layouts.public>
