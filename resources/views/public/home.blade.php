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
                    <circle cx="200" cy="200" r="182" fill="none" stroke="#F1F2EE" stroke-width="1.5"/>
                    <circle cx="200" cy="200" r="120" fill="none" stroke="#F1F2EE" stroke-width="1"/>
                    <circle cx="200" cy="200" r="52" fill="none" stroke="#F1F2EE" stroke-width="1"/>
                    <path d="M200 0v150M200 250v150M0 200h150M250 200h150" stroke="#F1F2EE" stroke-width="1.5"/>
                    <path d="M182 236h36M186 258h28M190 280h20M182 164h36M186 142h28M190 120h20" stroke="#F1F2EE" stroke-width="1.5"/>
                    <path d="M164 182v36M142 186v28M120 190v20M236 182v36M258 186v28M280 190v20" stroke="#F1F2EE" stroke-width="1.5"/>
                    {{-- UX audit cool-factor: this centre dot is the
                         only thing that moves. See .hero-reticle-dot
                         in app.css for the pulse rule (rotating sweep
                         was ripped out — read as radar). --}}
                    <circle class="hero-reticle-dot" cx="200" cy="200" r="3.5" fill="#6B7D3A"/>
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
                                <span class="dow">{{ \App\Support\EventDate::weekday($event->starts_at, $event->ends_at) }}</span>
                                <b>{{ \App\Support\EventDate::dayOfMonth($event->starts_at, $event->ends_at) }}</b>
                                <span class="mo">{{ \App\Support\EventDate::monthWithYear($event->starts_at, $event->ends_at) }}</span>
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

        <section class="block" id="divisions" style="padding-top:56px">
            <div class="wrap">
                <div class="sec-head">
                    <p class="label">Start here</p>
                    <h2>What are you interested in?</h2>
                    <p>Four divisions. The sports, clubs, ranges, matches, and suppliers for that division open on the next page.</p>
                </div>
                <div class="division-choices">
                    @foreach (\App\Enums\Division::publicCases() as $division)
                        <a class="division-choice" href="{{ route('divisions.show', $division->value) }}">
                            <span class="nm">{{ $division->getLabel() }}</span>
                            <span class="go">Open {{ strtolower($division->getLabel()) }} →</span>
                        </a>
                    @endforeach
                </div>
            </div>
        </section>
    </main>
</x-layouts.public>
