<x-mockups.v2.layout title="Find a match" active="matches">
    <div class="v2-wrap v2-page">
        <header class="v2-hero">
            <div class="v2-hero-copy">
                <p class="v2-kicker">Matches</p>
                <h1>{{ data_get($sportFilter, 'name') ?: ($division ? $division->getLabel() : 'Find a match') }}</h1>
                <p class="v2-lede">Discover shooting matches across South Africa. The list is the main view. Calendar and map are other ways to explore.</p>
            </div>
            <div class="v2-hero-art is-match" role="img" aria-label="Clay target range"></div>
        </header>
        <nav class="v2-tabs" aria-label="Match views">
            <a class="on" href="{{ $mk('mockups.v2.matches', request()->except(['page'])) }}"><x-mockups.v2.icon name="list" /> List</a>
            <a href="{{ $mk('mockups.v2.matches.calendar', request()->except(['page'])) }}"><x-mockups.v2.icon name="calendar" /> Calendar</a>
            <a href="{{ $mk('mockups.v2.matches.map', request()->except(['page'])) }}"><x-mockups.v2.icon name="map" /> Map</a>
        </nav>
        <form class="v2-filters" method="get">
            @include('mockups.v2.partials.keep')
            <label class="v2-field">
                <span><x-mockups.v2.icon name="target" /> Sport</span>
                <select name="sport">
                    <option value="">Any sport</option>
                    @foreach ($sports as $sport)
                        <option value="{{ $sport['slug'] }}" @selected(request('sport') === $sport['slug'])>{{ $sport['name'] }}</option>
                    @endforeach
                </select>
            </label>
            <label class="v2-field">
                <span><x-mockups.v2.icon name="pin" /> Province</span>
                <select name="province">
                    <option value="">Any province</option>
                    <option value="near" @selected(request('province') === 'near')>Near me</option>
                    @foreach ($provinces as $province)
                        <option value="{{ $province->value }}" @selected(request('province') === $province->value)>{{ $province->getLabel() }}</option>
                    @endforeach
                </select>
            </label>
            <label class="v2-field">
                <span><x-mockups.v2.icon name="calendar" /> From</span>
                <input type="date" name="from" value="{{ request('from') }}">
            </label>
            <label class="v2-field">
                <span><x-mockups.v2.icon name="calendar" /> To</span>
                <input type="date" name="to" value="{{ request('to') }}">
            </label>
            <label class="v2-field">
                <span><x-mockups.v2.icon name="sort" /> Sort</span>
                <select name="sort">
                    <option value="soonest" @selected(request('sort', 'soonest') === 'soonest')>Soonest</option>
                    <option value="closest" @selected(request('sort') === 'closest')>Closest</option>
                    <option value="recent" @selected(request('sort') === 'recent')>Recently added</option>
                </select>
            </label>
            <button class="v2-btn v2-btn-green" type="submit">Find matches</button>
        </form>
        <details class="v2-more" @if(request()->hasAny(['level', 'beginner', 'fee', 'confirmed', 'radius'])) open @endif>
            <summary>More filters</summary>
            <form class="v2-filters cols-4" method="get">
                @include('mockups.v2.partials.keep')
                @foreach (['sport', 'province', 'from', 'to', 'sort'] as $kept)
                    @if (request($kept))<input type="hidden" name="{{ $kept }}" value="{{ request($kept) }}">@endif
                @endforeach
                <label class="v2-field">
                    <span>Match level</span>
                    <select name="level">
                        <option value="">Any level</option>
                        @foreach (['club' => 'Club', 'series' => 'Series', 'provincial' => 'Provincial', 'national' => 'National', 'international' => 'International'] as $value => $label)
                            <option value="{{ $value }}" @selected(request('level') === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </label>
                <label class="v2-field">
                    <span>Entry fee</span>
                    <select name="fee">
                        <option value="">Any</option>
                        <option value="listed" @selected(request('fee') === 'listed')>Fee published</option>
                    </select>
                </label>
                <label class="v2-field">
                    <span>Distance from me</span>
                    <select name="radius">
                        <option value="">Any distance</option>
                        @foreach ([50, 100, 150, 300] as $km)
                            <option value="{{ $km }}" @selected((string) request('radius') === (string) $km)>Within {{ $km }} km</option>
                        @endforeach
                    </select>
                </label>
                <label class="v2-check"><input type="checkbox" name="beginner" value="1" @checked(request('beginner') === '1')> New shooter friendly</label>
                <label class="v2-check"><input type="checkbox" name="confirmed" value="1" @checked(request('confirmed') === '1')> Confirmed only</label>
                <button class="v2-btn v2-btn-green" type="submit">Apply</button>
            </form>
        </details>
        @include('mockups.v2.partials.ad-space', ['sponsors' => $sponsors, 'limit' => $sponsors->count()])
        <div class="v2-results-head">
            <strong>{{ $total }} {{ $total === 1 ? 'match' : 'matches' }}</strong>
            <span class="v2-switch"><span class="on">Upcoming</span></span>
        </div>
        <div class="v2-list">
            @forelse ($matches as $match)
                <x-mockups.v2.match-row :match="$match" />
            @empty
                <p class="v2-empty"><strong>{{ request()->except(['device', 'page', 'sort', 'theme']) ? 'No matches match these filters.' : 'No upcoming matches are listed yet.' }}</strong></p>
            @endforelse
        </div>
        @if ($lastPage > 1)
            <nav class="v2-pages" aria-label="Pagination">
                @if ($page > 1)
                    <a class="v2-btn v2-btn-line" href="{{ $mk('mockups.v2.matches', array_merge(request()->except('page'), ['page' => $page - 1])) }}">Previous</a>
                @endif
                <span>Page {{ $page }} of {{ $lastPage }}</span>
                @if ($page < $lastPage)
                    <a class="v2-btn v2-btn-line" href="{{ $mk('mockups.v2.matches', array_merge(request()->except('page'), ['page' => $page + 1])) }}">Next</a>
                @endif
            </nav>
        @endif
    </div>
</x-mockups.v2.layout>
