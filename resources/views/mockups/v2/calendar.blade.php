<x-mockups.v2.layout title="Calendar" active="matches">
    <div class="v2-wrap v2-page">
        <header class="v2-hero">
            <div class="v2-hero-copy">
                <p class="v2-kicker">Matches</p>
                <h1>{{ data_get($sportFilter, 'name') ?: ($division ? $division->getLabel() : 'Calendar') }}</h1>
                <p class="v2-lede">The same matches as the list, laid out by month. The list is the main view.</p>
            </div>
            <div class="v2-hero-art is-match" role="img" aria-label="Clay target range"></div>
        </header>
        <nav class="v2-tabs" aria-label="Match views">
            <a href="{{ $mk('mockups.v2.matches', request()->except(['page', 'month'])) }}"><x-mockups.v2.icon name="list" /> List</a>
            <a class="on" href="{{ $mk('mockups.v2.matches.calendar', request()->except(['page'])) }}"><x-mockups.v2.icon name="calendar" /> Calendar</a>
            <a href="{{ $mk('mockups.v2.matches.map', request()->except(['page', 'month'])) }}"><x-mockups.v2.icon name="map" /> Map</a>
        </nav>
        <form class="v2-filters" method="get">
            @include('mockups.v2.partials.keep')
            @if (request('month'))<input type="hidden" name="month" value="{{ request('month') }}">@endif
            <label class="v2-field"><span>Sport</span>
                <select name="sport"><option value="">Any sport</option>
                    @foreach ($sports as $sport)<option value="{{ $sport['slug'] }}" @selected(request('sport') === $sport['slug'])>{{ $sport['name'] }}</option>@endforeach
                </select>
            </label>
            <label class="v2-field"><span>Province</span>
                <select name="province"><option value="">Any province</option>
                    @foreach ($provinces as $province)<option value="{{ $province->value }}" @selected(request('province') === $province->value)>{{ $province->getLabel() }}</option>@endforeach
                </select>
            </label>
            <label class="v2-field"><span>From</span><input type="date" name="from" value="{{ request('from') }}"></label>
            <label class="v2-field"><span>To</span><input type="date" name="to" value="{{ request('to') }}"></label>
            <span></span>
            <button class="v2-btn v2-btn-green" type="submit">Find matches</button>
        </form>
        @include('mockups.v2.partials.ad-space', ['sponsors' => $sponsors, 'limit' => $sponsors->count()])
        <div class="v2-cal-nav">
            <h2>{{ $calendar['label'] }}</h2>
            <a class="v2-btn v2-btn-line" href="{{ $mk('mockups.v2.matches.calendar', array_merge(request()->except('page'), ['month' => $calendar['today']])) }}">Today</a>
            <a class="v2-btn v2-btn-line" href="{{ $mk('mockups.v2.matches.calendar', array_merge(request()->except('page'), ['month' => $calendar['prev']])) }}">Previous</a>
            <a class="v2-btn v2-btn-line" href="{{ $mk('mockups.v2.matches.calendar', array_merge(request()->except('page'), ['month' => $calendar['next']])) }}">Next</a>
        </div>
        <table class="v2-cal">
            <thead><tr>@foreach (['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'] as $day)<th>{{ $day }}</th>@endforeach</tr></thead>
            <tbody>
                @foreach ($calendar['weeks'] as $week)
                    <tr>
                        @foreach ($week as $day)
                            <td @class(['off' => ! $day['in_month'], 'today' => $day['is_today']])>
                                <span class="n">{{ $day['day'] }}</span>
                                @foreach (array_slice($day['events'], 0, 3) as $event)
                                    <a class="ev" href="{{ $mk('mockups.v2.match', ['slug' => $event['slug']]) }}">{{ $event['title'] }}</a>
                                @endforeach
                            </td>
                        @endforeach
                    </tr>
                @endforeach
            </tbody>
        </table>
        <div class="v2-agenda v2-list">
            @forelse ($calendar['agenda'] as $match)
                <x-mockups.v2.match-row :match="$match" />
            @empty
                <p class="v2-empty">Nothing listed in {{ $calendar['label'] }}.</p>
            @endforelse
        </div>
    </div>
</x-mockups.v2.layout>
