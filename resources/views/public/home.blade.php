<x-layouts.public title="Shooting matches in South Africa">
    <main id="main">
        <x-page-photo page="home" class="hero home-hero" style="padding:0">
            <div class="hero-in">
                <div>
                    <p class="label">The national register of South African shooting sport</p>
                    <h1>Find your <em>sport</em>.<br>Find your <em>club</em>.<br>Find your <em>match</em>.</h1>
                    <p class="lede">Every discipline, every province, one calendar. South African shooting sport in one place.</p>
                    <p class="hero-weekend">
                        <a class="btn lime" href="{{ route('calendar', ['weekend' => 1]) }}">What's shooting this weekend?</a>
                    </p>
                </div>
                <div class="hero-rail">
                    <div class="rail-head">
                        <h3>{{ $railLabel }}</h3>
                        <span class="label">Nationwide</span>
                    </div>
                    @forelse ($rail as $event)
                        <a class="rail-item" href="{{ route('matches.show', $event->slug) }}">
                            <div class="rail-date">
                                <span class="mo">{{ \App\Support\EventDate::monthWithYear($event->starts_at, $event->ends_at) }}</span>
                                <b>{{ \App\Support\EventDate::dayOfMonth($event->starts_at, $event->ends_at) }}</b>
                            </div>
                            <div class="rail-body">
                                <div class="t">{{ $event->title }}</div>
                                @php
                                    $railMeta = collect([
                                        $event->listedLocation(),
                                        $event->primaryDiscipline()?->name ?? $event->disciplines->first()?->name,
                                    ])->filter();
                                @endphp
                                @if ($railMeta->isNotEmpty())
                                    <div class="m">{{ $railMeta->implode(' · ') }}</div>
                                @endif
                            </div>
                        </a>
                    @empty
                        <p class="empty">No upcoming matches listed yet.</p>
                    @endforelse
                </div>
            </div>
        </x-page-photo>

        <div class="strip home-stats">
            <div class="strip-in">
                {{-- Bundle A #4: traction stats only. Drop Provinces
                     (geography, not traction) and Industry (gated until
                     seeded). Clubs & series combine membership clubs
                     with branded series so the number matches the
                     directory the visitor actually opens. --}}
                <div><span>Matches</span><b>{{ $stats['matches'] }}</b></div>
                <div><span>Ranges</span><b>{{ $stats['ranges'] }}</b></div>
                <div><span>Disciplines</span><b>{{ $stats['disciplines'] }}</b></div>
                <div><span>Clubs</span><b>{{ $stats['clubs'] + $stats['series'] }}</b></div>
            </div>
        </div>

        <section class="block home-divisions" id="divisions" style="padding-top:36px">
            <div class="wrap">
                <div class="home-headrow">
                    <div class="sec-head">
                        <p class="label">Start here</p>
                        <h2>What are you interested in?</h2>
                    </div>
                    <a href="{{ route('disciplines.index') }}">Explore all sports</a>
                </div>
                <div class="division-choices">
                    @foreach (\App\Enums\Division::publicCases() as $division)
                        <a @class(['division-choice', 'has-photo' => filled($division->cardPhoto())]) href="{{ route('divisions.show', $division->value) }}" @if (filled($division->cardPhoto())) style="--hero-image: url('{{ asset($division->cardPhoto()) }}')" @endif>
                            <span class="kicker">Division</span>
                            <span class="nm">{{ $division->getLabel() }}</span>
                            <span class="go">Open {{ strtolower($division->getLabel()) }}</span>
                        </a>
                    @endforeach
                </div>
            </div>
        </section>

        <section class="block home-follow">
            <div class="wrap">
                <div class="home-headrow">
                    <div class="sec-head">
                        <p class="label">Next 30 days</p>
                        <h2>Coming up</h2>
                    </div>
                    <a href="{{ route('calendar') }}">View all matches</a>
                </div>
                <div class="dir-list">
                    @forelse ($upcoming as $event)
                        <a class="dir-match" href="{{ route('matches.show', $event->slug) }}">
                            <span class="dir-date">
                                <b>{{ \App\Support\EventDate::dayOfMonth($event->starts_at, $event->ends_at) }}</b>
                                <span>{{ \App\Support\EventDate::monthWithYear($event->starts_at, $event->ends_at) }}</span>
                            </span>
                            <span class="dir-main">
                                <strong>{{ $event->title }}</strong>
                                @php
                                    $upcomingMeta = collect([
                                        $event->listedLocation(),
                                        ($event->primaryDiscipline() ?? $event->disciplines->first())?->name,
                                    ])->filter();
                                @endphp
                                @if ($upcomingMeta->isNotEmpty())
                                    <span>{{ $upcomingMeta->implode(' · ') }}</span>
                                @endif
                            </span>
                            <span class="dir-chev" aria-hidden="true">›</span>
                        </a>
                    @empty
                        <p class="empty">No upcoming matches are listed yet.</p>
                    @endforelse
                </div>
            </div>
        </section>

        <section class="block home-follow">
            <div class="wrap">
                <div class="sec-head">
                    <h2>Find a club or range</h2>
                </div>
                <div class="home-choices">
                    <a href="{{ route('clubs.index') }}">
                        <span class="label">Clubs and series</span>
                        <strong>Find people shooting the same sports.</strong>
                    </a>
                    <a href="{{ route('ranges.index') }}">
                        <span class="label">Ranges</span>
                        <strong>Find a range near you.</strong>
                    </a>
                </div>
            </div>
        </section>

        <section class="block home-follow">
            <div class="wrap">
                <div class="dir-note">
                    <h2>New to shooting?</h2>
                    <p>Not sure where to start? Learn about the different shooting disciplines and how to get involved.</p>
                    <a class="btn dir-line" href="{{ route('disciplines.index') }}">Explore shooting sports</a>
                </div>
            </div>
        </section>
    </main>
</x-layouts.public>
