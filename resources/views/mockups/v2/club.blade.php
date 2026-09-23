<x-mockups.v2.layout :title="data_get($club, 'name', 'Club')" active="clubs">
    <div class="v2-wrap v2-page">
        @if (! $club)
            <p class="v2-empty"><strong>No clubs in the register yet.</strong></p>
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
                $place = ($club['place'] ?? null) !== '—' ? ($club['place'] ?? '') : '';
            @endphp
            <header style="padding-top:28px">
                @if ($club['logo'])<img class="v2-logo" src="{{ $club['logo'] }}" alt="">@endif
                <h1>{{ $club['name'] }}</h1>
                @if ($place !== '')<p class="v2-lede">{{ $place }}</p>@endif
                @if ($club['disciplines'] !== [])
                    <div class="v2-tags">@foreach ($club['disciplines'] as $name)<span class="v2-tag">{{ $name }}</span>@endforeach</div>
                @endif
                <div class="v2-actions">
                    <a class="v2-btn v2-btn-line" href="{{ $mk('mockups.v2.club', array_merge(['slug' => $club['slug']], $followQuery)) }}" aria-pressed="{{ $following ? 'true' : 'false' }}">{{ $following ? 'Following' : 'Follow' }}</a>
                    @if ($club['website'])<a class="v2-btn v2-btn-line" href="{{ $club['website'] }}">Website</a>@endif
                    @if ($club['email'])<a class="v2-btn v2-btn-line" href="mailto:{{ $club['email'] }}">Contact</a>
                    @elseif ($club['phone'])<a class="v2-btn v2-btn-line" href="tel:{{ $club['phone'] }}">Contact</a>@endif
                </div>
            </header>
            @if ($upcoming->isNotEmpty())
                <section class="v2-section">
                    <h2>{{ $upcoming->count() }} upcoming {{ \Illuminate\Support\Str::plural('match', $upcoming->count()) }}</h2>
                    <div class="v2-list" style="margin-top:12px">
                        <x-mockups.v2.match-row :match="$next" />
                        @foreach ($rest as $match)<x-mockups.v2.match-row :match="$match" />@endforeach
                    </div>
                </section>
            @endif
            @if ($club['description'])<section class="v2-section"><h2>About</h2><div class="v2-prose"><p>{{ $club['description'] }}</p></div></section>@endif
            @if ($club['range_slug'])<section class="v2-section"><h2>Where we shoot</h2><p><a class="v2-textlink" href="{{ $mk('mockups.v2.range', ['slug' => $club['range_slug']]) }}">{{ $club['range'] }}</a></p></section>@endif
            @if ($club['visitors_welcome'])<section class="v2-section"><h2>Membership</h2><p>Visitors are welcome.</p></section>@endif
            @if ($club['email'] || $club['phone'] || $club['website'])
                <section class="v2-section">
                    <h2>Contact</h2>
                    <dl class="v2-dl">
                        @if ($club['email'])<dt>Email</dt><dd><a href="mailto:{{ $club['email'] }}">{{ $club['email'] }}</a></dd>@endif
                        @if ($club['phone'])<dt>Phone</dt><dd><a href="tel:{{ $club['phone'] }}">{{ $club['phone'] }}</a></dd>@endif
                        @if ($club['website'])<dt>Website</dt><dd><a href="{{ $club['website'] }}">{{ $club['website'] }}</a></dd>@endif
                    </dl>
                </section>
            @endif
            @if ($club['facebook'])<section class="v2-section"><h2>Photos and links</h2><p><a class="v2-textlink" href="{{ $club['facebook'] }}">Facebook</a></p></section>@endif
        @endif
    </div>
</x-mockups.v2.layout>
