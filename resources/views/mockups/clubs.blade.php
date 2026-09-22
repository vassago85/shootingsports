<x-mockups.layout title="Find a shooting club" active="clubs">
    <div class="wrap" style="padding-bottom:40px">
        <header class="mk-pagehead">
            <p class="label">Clubs</p>
            <h1>Find a shooting club</h1>
            <p class="mk-lede">Clubs, series and associations on the register. The current directory includes series alongside clubs.</p>
        </header>
        <form class="mk-filters" method="get">
            @if (request('device') === 'mobile')
                <input type="hidden" name="device" value="mobile">
            @endif
            <label class="field">
                <span>Sport</span>
                <select name="sport">
                    <option value="">Any sport</option>
                    @foreach ($sports as $sport)
                        <option value="{{ $sport['slug'] }}" @selected(request('sport') === $sport['slug'])>{{ $sport['name'] }}</option>
                    @endforeach
                </select>
            </label>
            <label class="field">
                <span>Province</span>
                <select name="province">
                    <option value="">Any province</option>
                    @foreach ($provinces as $province)
                        <option value="{{ $province->value }}" @selected(request('province') === $province->value)>{{ $province->getLabel() }}</option>
                    @endforeach
                </select>
            </label>
            <label class="field">
                <span>Town</span>
                <input type="search" name="town" value="{{ request('town') }}" placeholder="Town">
            </label>
            <label class="mk-check"><input type="checkbox" name="visitors" value="1" @checked(request('visitors') === '1')> Visitors welcome</label>
            <label class="mk-check"><input type="checkbox" name="members" value="1" @checked(request('members') === '1')> New members</label>
            <button class="btn" type="submit">Filter</button>
        </form>
        @if ($membersFilterUnused)
            <aside class="mk-internal">
                <span>Reviewer note</span>
                <p>Accepting new members is not a field on clubs. This filter matches nothing until that is stored. Visitors welcome is a real field and does filter.</p>
            </aside>
        @endif
        @forelse ($clubs as $club)
            <a class="mk-row" href="{{ $mk('mockups.club', ['slug' => $club['slug']]) }}">
                <span class="mk-row-id">
                    @if ($club['logo'])
                        <img class="mk-logo" src="{{ $club['logo'] }}" alt="">
                    @endif
                    <span>
                        <span class="mk-row-title">{{ $club['name'] }}</span>
                        <span class="mk-sub">{{ $club['place'] ?: 'Location not listed' }}</span>
                    </span>
                </span>
                <span class="mk-row-meta">
                    {{ $club['disciplines'] !== [] ? implode(' · ', array_slice($club['disciplines'], 0, 4)) : 'Sports not listed' }}
                    @if ($club['range'])<br>{{ $club['range'] }}@endif
                </span>
                <span class="mk-count">{{ $club['upcoming_count'] }} upcoming</span>
            </a>
        @empty
            <p class="mk-empty"><strong>No clubs match these filters.</strong></p>
        @endforelse
    </div>
</x-mockups.layout>
