<x-mockups.v2.layout title="{{ $division ? $division->getLabel() : 'Shooting sports' }}" active="{{ $division ? 'home' : 'sports' }}">
    <div class="v2-wrap v2-page">
        <header class="v2-hero">
            <div class="v2-hero-copy">
                <p class="v2-kicker">{{ $division ? 'Division' : 'Sports' }}</p>
                <h1>{{ $division ? $division->getLabel() : 'Shooting sports' }}</h1>
                <p class="v2-lede">
                    @if ($division)
                        Choose a discipline. If it has a sub-discipline, that is the next step. The match list comes after you have chosen.
                    @else
                        Disciplines shot in South Africa. Open one to see what happens at a match, which clubs offer it, and what is coming up.
                    @endif
                </p>
            </div>
            <div class="v2-hero-art is-club" role="img" aria-label="Outdoor shooting range"></div>
        </header>
        @if ($division)
            @include('mockups.v2.partials.ad-space', ['sponsors' => $sponsors, 'limit' => $sponsors->count()])
        @endif
        <form class="v2-filters cols-3" method="get" action="{{ $mk('mockups.v2.sports') }}">
            @include('mockups.v2.partials.keep')
            @if ($division)<input type="hidden" name="division" value="{{ $division->value }}">@endif
            <label class="v2-field"><span><x-mockups.v2.icon name="search" /> Search</span><input type="search" name="q" value="{{ request('q') }}" placeholder="Precision rifle, IPSC, trap"></label>
            <label class="v2-field"><span>Category</span>
                <select name="family">
                    <option value="">All</option>
                    @foreach (['rifle' => 'Rifle', 'handgun' => 'Handgun', 'shotgun' => 'Shotgun', 'airgun' => 'Airgun', 'multi' => 'Multi-gun'] as $value => $label)
                        <option value="{{ $value }}" @selected(request('family') === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </label>
            <span></span>
            <button class="v2-btn v2-btn-green" type="submit">Search</button>
        </form>
        <div class="v2-list">
            @forelse ($sports as $sport)
                <a class="v2-row" style="grid-template-columns:minmax(0,1fr) auto 16px" href="{{ ($sport['children'] ?? []) !== [] ? $mk('mockups.v2.sport', ['slug' => $sport['slug']]) : $mk('mockups.v2.matches', ['sport' => $sport['slug']]) }}">
                    <span>
                        <span class="v2-name">{{ $sport['name'] }}</span>
                        @if (filled($sport['blurb'] ?? null))<span class="v2-sport">{{ $sport['blurb'] }}</span>@endif
                        @if (filled($sport['family_label'] ?? null))<span class="v2-tags"><span class="v2-tag">{{ $sport['family_label'] }}</span></span>@endif
                    </span>
                    <span class="v2-side">
                        @if (($sport['upcoming_count'] ?? 0) > 0)
                            <span>{{ $sport['upcoming_count'] }} upcoming</span>
                        @endif
                        @if (($sport['clubs_count'] ?? 0) > 0)
                            <span>{{ $sport['clubs_count'] }} {{ \Illuminate\Support\Str::plural('club', $sport['clubs_count']) }}</span>
                        @endif
                    </span>
                    <span class="v2-chev" aria-hidden="true">›</span>
                </a>
            @empty
                <p class="v2-empty"><strong>No sports match this search.</strong></p>
            @endforelse
        </div>
    </div>
</x-mockups.v2.layout>
