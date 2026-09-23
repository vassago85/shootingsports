<x-mockups.layout title="Find a shooting club" active="clubs">
    <div class="wrap" style="padding-bottom:40px">
        <header class="mk-pagehead">
            <p class="label">Clubs</p>
            <h1>Find a shooting club</h1>
            <p class="mk-lede">Find people shooting the same sports.</p>
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
            <button class="btn" type="submit">Filter</button>
        </form>
        @forelse ($clubs as $club)
            @php
                $place = collect([$club['town'] ?? null, $club['province'] ?? null])->filter(fn (?string $part): bool => filled($part) && $part !== '—')->implode(' · ');
            @endphp
            <a class="mk-dir" href="{{ $mk('mockups.club', ['slug' => $club['slug']]) }}">
                <span class="mk-dir-name">{{ $club['name'] }}</span>
                @if ($place !== '')
                    <span class="mk-dir-place">{{ $place }}</span>
                @endif
                @if ($club['disciplines'] !== [])
                    <span class="mk-dir-meta">{{ implode(' · ', array_slice($club['disciplines'], 0, 4)) }}</span>
                @endif
                @if ($club['upcoming_count'])
                    <span class="mk-dir-count">{{ $club['upcoming_count'] }} upcoming {{ \Illuminate\Support\Str::plural('match', $club['upcoming_count']) }}</span>
                @endif
            </a>
        @empty
            <p class="mk-empty"><strong>No clubs match these filters.</strong></p>
        @endforelse
    </div>
</x-mockups.layout>
