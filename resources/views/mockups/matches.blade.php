<x-mockups.layout title="Find a match" description="Discover shooting matches across South Africa." active="matches">
    <div class="wrap" style="padding-bottom:40px">
        <header class="mk-pagehead">
            <p class="label">Matches</p>
            <h1>{{ data_get($sportFilter, 'name') ?: ($division ? $division->getLabel() : 'Find a match') }}</h1>
            <p class="mk-lede">
                @if ($sportFilter)
                    The match list for {{ $sportFilter['name'] }}. Calendar is the other view of the same events.
                @else
                    Discover shooting matches across South Africa. The list is the main view. Calendar and map are the other ways to read it.
                @endif
            </p>
        </header>
        <x-mockups.match-views />
        <x-mockups.match-filters :sports="$sports" :provinces="$provinces" />
        @php
            $available = collect($sponsors)->unique('slug')->values();
            $sponsorSlugs = $sportSlugs !== [] ? $sportSlugs : $divisionSports;
            $scopedSponsors = $sponsorSlugs === []
                ? collect()
                : $available->filter(
                    fn (array $sponsor): bool => array_intersect($sponsor['discipline_slugs'] ?? [], $sponsorSlugs) !== []
                )->values();
            $leaderboard = $scopedSponsors->isNotEmpty() ? $scopedSponsors : $available;
            $shown = $leaderboard->take(1)->pluck('slug')->filter()->all();
            $inFeed = $available->reject(
                fn (array $sponsor): bool => in_array($sponsor['slug'], $shown, true)
            )->values();
        @endphp
        @include('mockups.partials.ad-space', ['sponsors' => $leaderboard, 'limit' => 1])
        @if (request('radius') && ! request('lat'))
            <p class="mk-support">Choose Near me in the province list to measure distance. A radius on its own does not guess a location.</p>
        @endif
        @if ($unlocated > 0)
            <aside class="mk-internal">
                <span>Reviewer note</span>
                <p>{{ $unlocated }} {{ $unlocated === 1 ? 'match was' : 'matches were' }} left out of this distance filter because the range has no GPS. That note stays off the public list.</p>
            </aside>
        @endif
        <p class="mk-support" style="margin-top:14px">{{ $total }} {{ $total === 1 ? 'match' : 'matches' }}</p>
        @forelse ($matches as $match)
            <x-mockups.match-row :match="$match" />
            @if ($loop->iteration === 2 && $inFeed->isNotEmpty())
                @include('mockups.partials.ad-space', ['sponsors' => $inFeed, 'limit' => 1])
            @endif
        @empty
            <div class="mk-empty">
                <strong>{{ request()->except(['device', 'page', 'sort']) ? 'No matches match these filters.' : 'No upcoming matches are listed yet.' }}</strong>
                @if (request('beginner'))
                    New-shooter-friendly is a real flag on the register. None of the current upcoming matches carry it.
                @else
                    Clear a filter or check another month.
                @endif
            </div>
        @endforelse
        @if ($matches->count() < 2 && $inFeed->isNotEmpty())
            @include('mockups.partials.ad-space', ['sponsors' => $inFeed, 'limit' => 1])
        @endif
        @if ($lastPage > 1)
            <nav class="mk-pages" aria-label="Pagination">
                @if ($page > 1)
                    <a href="{{ $mk('mockups.matches', array_merge(request()->except('page'), ['page' => $page - 1])) }}">Previous</a>
                @endif
                <span>Page {{ $page }} of {{ $lastPage }}</span>
                @if ($page < $lastPage)
                    <a href="{{ $mk('mockups.matches', array_merge(request()->except('page'), ['page' => $page + 1])) }}">Next</a>
                @endif
            </nav>
        @endif
    </div>
</x-mockups.layout>
