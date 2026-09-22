<x-mockups.layout title="{{ $division ? $division->getLabel() : 'Shooting sports' }}" description="Shooting disciplines in South Africa." active="{{ $division ? 'home' : 'sports' }}">
    <div class="wrap" style="padding-bottom:40px">
        <header class="mk-pagehead">
            <p class="label">{{ $division ? 'Division' : 'Sports' }}</p>
            <h1>{{ $division ? $division->getLabel() : 'Shooting sports' }}</h1>
            <p class="mk-lede">
                @if ($division)
                    Choose a discipline. If it has a sub-discipline, that is the next step. The match list comes after you have chosen. Calendar stays available as the other view.
                @else
                    Disciplines shot in South Africa. Open one to see what happens at a match, which clubs offer it, and what is coming up.
                @endif
            </p>
        </header>
        @if ($division)
            @php
                $categorySponsors = collect($sponsors)->filter(
                    fn (array $sponsor): bool => array_intersect($sponsor['discipline_slugs'] ?? [], $divisionSports) !== []
                )->values();
            @endphp
            @include('mockups.partials.ad-space', ['sponsors' => $categorySponsors, 'limit' => 1])
        @endif
        <form class="mk-filters" method="get" action="{{ $mk('mockups.sports') }}">
            @if (request('device') === 'mobile')
                <input type="hidden" name="device" value="mobile">
            @endif
            @if ($division)
                <input type="hidden" name="division" value="{{ $division->value }}">
            @endif
            <label class="field grow">
                <span>Search</span>
                <input type="search" name="q" value="{{ request('q') }}" placeholder="Precision rifle, IPSC, trap">
            </label>
            <label class="field">
                <span>Category</span>
                <select name="family">
                    <option value="">All</option>
                    @foreach (['rifle' => 'Rifle', 'handgun' => 'Handgun', 'shotgun' => 'Shotgun', 'airgun' => 'Airgun', 'multi' => 'Multi-gun'] as $value => $label)
                        <option value="{{ $value }}" @selected(request('family') === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </label>
            <button class="btn" type="submit">Search</button>
        </form>
        <div class="mk-tiles" style="margin-top:18px">
            @forelse ($sports as $sport)
                @php
                    $hasChildren = ($sport['children'] ?? []) !== [];
                    $href = $hasChildren
                        ? $mk('mockups.sport', ['slug' => $sport['slug']])
                        : $mk('mockups.matches', ['sport' => $sport['slug']]);
                @endphp
                <a class="mk-tile" href="{{ $href }}">
                    <span class="fam">{{ $sport['family_label'] }}</span>
                    <strong>{{ $sport['name'] }}</strong>
                    <span>{{ $sport['blurb'] }}</span>
                    <em>
                        @if ($hasChildren)
                            {{ count($sport['children']) }} {{ count($sport['children']) === 1 ? 'sub-discipline' : 'sub-disciplines' }}
                        @else
                            Match list
                        @endif
                        · {{ $sport['upcoming_count'] }} upcoming
                    </em>
                </a>
            @empty
                <p class="mk-empty"><strong>No sports match that search.</strong></p>
            @endforelse
        </div>
    </div>
</x-mockups.layout>
