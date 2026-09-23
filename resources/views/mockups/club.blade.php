<x-mockups.layout title="{{ data_get($club, 'name', 'Club') }}" active="clubs" :sample="data_get($club, 'name')">
    <div class="wrap" style="padding-bottom:48px">
        @if (! $club)
            <div class="mk-empty"><strong>No clubs in the register yet.</strong></div>
        @else
            @php
                $following = $follows['clubs']->contains($club['slug']);
                $nextClubs = $following
                    ? $follows['clubs']->reject(fn ($s) => $s === $club['slug'])->values()
                    : $follows['clubs']->push($club['slug'])->unique()->values();
                $followQuery = array_filter([
                    'sport' => $follows['sports']->all(),
                    'club' => $nextClubs->all(),
                    'province' => $follows['province'],
                ], fn ($value) => $value !== '' && $value !== []);
                $upcoming = collect($club['upcoming']);
                $next = $upcoming->first();
                $rest = $upcoming->slice(1)->values();
            @endphp
            <header class="mk-pagehead">
                @if ($club['logo'])
                    <img class="mk-logo mk-logo-lg" src="{{ $club['logo'] }}" alt="">
                @endif
                <h1>{{ $club['name'] }}</h1>
                @if ($club['place'])
                    <p class="mk-lede">{{ $club['place'] }}</p>
                @endif
                @if ($club['disciplines'] !== [])
                    <p class="mk-sportline">{{ implode(' · ', $club['disciplines']) }}</p>
                @endif
                <div class="mk-actions">
                    <a class="btn ghost @if ($following) mk-follow is-on @endif" href="{{ $mk('mockups.club', array_merge(['slug' => $club['slug']], $followQuery)) }}" aria-pressed="{{ $following ? 'true' : 'false' }}">{{ $following ? 'Following' : 'Follow' }}</a>
                    @if ($club['website'])
                        <a class="btn ghost" href="{{ $club['website'] }}">Website</a>
                    @endif
                    @if ($club['email'])
                        <a class="btn ghost" href="mailto:{{ $club['email'] }}">Contact</a>
                    @elseif ($club['phone'])
                        <a class="btn ghost" href="tel:{{ $club['phone'] }}">Contact</a>
                    @endif
                </div>
            </header>

            @if ($upcoming->isNotEmpty())
                <section class="mk-section">
                    <h2>{{ $upcoming->count() }} upcoming {{ \Illuminate\Support\Str::plural('match', $upcoming->count()) }}</h2>
                    <h3 class="mk-kicker">Next up</h3>
                    <x-mockups.match-row :match="$next" />
                    @if ($rest->isNotEmpty())
                        <h3 class="mk-kicker">Upcoming</h3>
                        @foreach ($rest as $match)
                            <x-mockups.match-row :match="$match" />
                        @endforeach
                    @endif
                    <p style="margin-top:12px"><a class="mk-textlink" href="{{ $mk('mockups.matches.calendar', ['club' => $club['slug']]) }}">Upcoming calendar</a></p>
                </section>
            @endif

            @if ($club['description'])
                <section class="mk-section">
                    <h2>About</h2>
                    <div class="mk-prose"><p>{{ $club['description'] }}</p></div>
                </section>
            @endif

            @if ($club['range_slug'])
                <section class="mk-section">
                    <h2>Where we shoot</h2>
                    <p><a href="{{ $mk('mockups.range', ['slug' => $club['range_slug']]) }}">{{ $club['range'] }}</a></p>
                </section>
            @endif

            @if ($club['disciplines'] !== [])
                <section class="mk-section">
                    <h2>Disciplines</h2>
                    <div class="mk-meta">
                        @foreach ($club['discipline_slugs'] as $index => $slug)
                            <a href="{{ $mk('mockups.sport', ['slug' => $slug]) }}">{{ $club['disciplines'][$index] }}</a>
                        @endforeach
                    </div>
                </section>
            @endif

            @if ($club['visitors_welcome'])
                <section class="mk-section">
                    <h2>Membership</h2>
                    <p>Visitors are welcome.</p>
                </section>
            @endif

            @if ($club['email'] || $club['phone'] || $club['website'])
                <section class="mk-section">
                    <h2>Contact</h2>
                    <dl class="mk-dl">
                        @if ($club['email'])<dt>Email</dt><dd><a href="mailto:{{ $club['email'] }}">{{ $club['email'] }}</a></dd>@endif
                        @if ($club['phone'])<dt>Phone</dt><dd><a href="tel:{{ $club['phone'] }}">{{ $club['phone'] }}</a></dd>@endif
                        @if ($club['website'])<dt>Website</dt><dd><a href="{{ $club['website'] }}">{{ $club['website'] }}</a></dd>@endif
                    </dl>
                </section>
            @endif

            @if ($club['facebook'])
                <section class="mk-section">
                    <h2>Photos and links</h2>
                    <p><a href="{{ $club['facebook'] }}">Facebook</a></p>
                </section>
            @endif
        @endif
    </div>
</x-mockups.layout>
