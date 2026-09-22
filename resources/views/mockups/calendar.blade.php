<x-mockups.layout title="Calendar" description="Shooting matches by month." active="matches">
    <div class="wrap" style="padding-bottom:40px">
        <header class="mk-pagehead">
            <p class="label">Matches</p>
            <h1>{{ data_get($sportFilter, 'name') ?: ($division ? $division->getLabel() : 'Calendar') }}</h1>
            <p class="mk-lede">
                @if ($sportFilter)
                    The calendar for {{ $sportFilter['name'] }}. The match list is the main view of these events.
                @elseif ($division)
                    Matches in this division, on the month calendar. The list is the main view.
                @else
                    The same matches as the list, laid out by month. The list is the main view. Colour marks the discipline family.
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
        <div class="mk-cal-nav">
            <h2>{{ $calendar['label'] }}</h2>
            <a class="btn ghost" href="{{ $mk('mockups.matches.calendar', array_merge(request()->except('page'), ['month' => $calendar['today']])) }}">Today</a>
            <a class="btn ghost" href="{{ $mk('mockups.matches.calendar', array_merge(request()->except('page'), ['month' => $calendar['prev']])) }}">Previous</a>
            <a class="btn ghost" href="{{ $mk('mockups.matches.calendar', array_merge(request()->except('page'), ['month' => $calendar['next']])) }}">Next</a>
        </div>
        <table class="mk-cal">
            <thead>
                <tr>
                    @foreach (['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'] as $day)
                        <th>{{ $day }}</th>
                    @endforeach
                </tr>
            </thead>
            <tbody>
                @foreach ($calendar['weeks'] as $week)
                    <tr>
                        @foreach ($week as $day)
                            <td @class(['off' => ! $day['in_month'], 'today' => $day['is_today']])>
                                <span class="n">{{ $day['day'] }}</span>
                                @foreach (array_slice($day['events'], 0, 3) as $event)
                                    <a class="ev" data-family="{{ $event['family'] }}" href="{{ $mk('mockups.match', ['slug' => $event['slug']]) }}">{{ $event['title'] }}</a>
                                @endforeach
                                @if (count($day['events']) > 3)
                                    <span class="n">+{{ count($day['events']) - 3 }}</span>
                                @endif
                            </td>
                        @endforeach
                    </tr>
                @endforeach
            </tbody>
        </table>
        <div class="mk-agenda">
            @forelse ($calendar['agenda'] as $match)
                <x-mockups.match-row :match="$match" />
                @if ($loop->iteration === 2 && $inFeed->isNotEmpty())
                    @include('mockups.partials.ad-space', ['sponsors' => $inFeed, 'limit' => 1])
                @endif
            @empty
                <p class="mk-empty"><strong>Nothing listed in {{ $calendar['label'] }}.</strong> Try the next month.</p>
            @endforelse
            @if (count($calendar['agenda']) < 2 && $inFeed->isNotEmpty())
                @include('mockups.partials.ad-space', ['sponsors' => $inFeed, 'limit' => 1])
            @endif
        </div>
    </div>
</x-mockups.layout>
