<x-mockups.v2.layout title="Find a shooting club" active="clubs">
    <div class="v2-wrap v2-page">
        <header class="v2-hero">
            <div class="v2-hero-copy">
                <p class="v2-kicker">Clubs</p>
                <h1>Find a shooting club</h1>
                <p class="v2-lede">Find people shooting the same sports.</p>
            </div>
            <div class="v2-hero-art is-club" role="img" aria-label="Outdoor shooting range"></div>
        </header>
        <form class="v2-filters cols-4" method="get">
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
                    @foreach ($provinces as $province)
                        <option value="{{ $province->value }}" @selected(request('province') === $province->value)>{{ $province->getLabel() }}</option>
                    @endforeach
                </select>
            </label>
            <label class="v2-field">
                <span><x-mockups.v2.icon name="pin" /> Town</span>
                <input type="search" name="town" value="{{ request('town') }}" placeholder="Town or area">
            </label>
            <label class="v2-check"><input type="checkbox" name="visitors" value="1" @checked(request('visitors') === '1')> Visitors welcome</label>
            <button class="v2-btn v2-btn-green" type="submit">Filter</button>
        </form>
        @if ($membersFilterUnused)
            <aside class="v2-note"><span>Reviewer note</span>The register does not record whether a club is accepting members, so that filter is not shown.</aside>
        @endif
        <div class="v2-results-head"><strong>{{ $clubs->count() }} {{ \Illuminate\Support\Str::plural('club', $clubs->count()) }}</strong></div>
        <div class="v2-split clubs">
            <div class="v2-list">
                @forelse ($clubs as $club)
                    <x-mockups.v2.club-row :club="$club" />
                @empty
                    <p class="v2-empty"><strong>No clubs match these filters.</strong></p>
                @endforelse
            </div>
            <aside>
                <div class="v2-sidebox soft">
                    <h2>List your club</h2>
                    <p>Get your club onto the ShootingSports register and reach more shooters across South Africa.</p>
                    <a class="v2-btn v2-btn-line" href="{{ $mk('mockups.manage.profile') }}">List your club</a>
                </div>
                @php
                    $popular = $sports
                        ->filter(fn (array $sport): bool => ($sport['parent_slug'] ?? null) === null && ($sport['clubs_count'] ?? 0) > 0)
                        ->sortByDesc('clubs_count')
                        ->take(5);
                @endphp
                @if ($popular->isNotEmpty())
                    <div class="v2-sidebox">
                        <h2>Popular sports</h2>
                        @foreach ($popular as $sport)
                            <a class="v2-sportline" href="{{ $mk('mockups.v2.sport', ['slug' => $sport['slug']]) }}">
                                <strong>{{ $sport['name'] }}</strong>
                                <span>{{ $sport['clubs_count'] }} {{ \Illuminate\Support\Str::plural('club', $sport['clubs_count']) }}</span>
                            </a>
                        @endforeach
                        <p style="margin-top:12px"><a class="v2-textlink" href="{{ $mk('mockups.v2.sports') }}">View all sports</a></p>
                    </div>
                @endif
                <div class="v2-sidebox soft">
                    <h2>New to shooting?</h2>
                    <p>Not sure where to start? Learn about the different shooting disciplines and how to get involved.</p>
                    <a class="v2-textlink" href="{{ $mk('mockups.v2.sports') }}">Explore shooting sports</a>
                </div>
            </aside>
        </div>
    </div>
</x-mockups.v2.layout>
