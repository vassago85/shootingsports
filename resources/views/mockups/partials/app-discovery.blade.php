@php
    $sheet = request()->string('sheet')->toString();
    $groupLabel = function (?string $date): string {
        if ($date === null || $date === '') {
            return 'Upcoming';
        }

        $when = \Carbon\Carbon::parse($date, 'Africa/Johannesburg');
        $now = now()->timezone('Africa/Johannesburg');

        if ($when->isSameWeek($now)) {
            return 'This week';
        }

        if ($when->isSameWeek($now->copy()->addWeek())) {
            return 'Next week';
        }

        return $when->format('F');
    };
@endphp

@if ($screen === 'matches')
    <div class="app-pad">
        <div class="app-chipbar" role="group" aria-label="Filters">
            <a class="app-chip" href="{{ $app('search') }}">Following</a>
            <a @class(['app-chip', 'on' => ($close['gps'] ?? false) === true]) href="{{ $app('matches', ['near' => 'denied']) }}" data-app-near="{{ $app('matches', ['near' => '1', 'km' => 100]) }}">Near me</a>
            <a class="app-chip" href="{{ $app('matches', ['sheet' => 'sport']) }}">Sport</a>
            <a class="app-chip" href="{{ $app('calendar') }}">Date</a>
            <a @class(['app-chip', 'on' => $sheet === 'province']) href="{{ $app('matches', ['sheet' => $sheet === 'province' ? null : 'province']) }}">{{ ($close['place'] ?? null) ?: 'Province' }}</a>
        </div>

        @if ($sheet === 'province')
            <div class="app-chipbar" role="group" aria-label="Province">
                <a @class(['app-chip', 'on' => ($close['using_home'] ?? false) === true]) href="{{ $app('matches', ['province' => null]) }}">{{ $close['home_place'] }}</a>
                @foreach ($places as $province)
                    @if ($province->value !== $close['home'])
                        <a @class(['app-chip', 'on' => $close['province'] === $province->value && ($close['using_home'] ?? false) !== true]) href="{{ $app('matches', ['province' => $province->value]) }}">{{ $province->getLabel() }}</a>
                    @endif
                @endforeach
                <a @class(['app-chip', 'on' => ($close['anywhere'] ?? false) === true]) href="{{ $app('matches', ['province' => 'all']) }}">Anywhere</a>
            </div>
        @elseif ($sheet === 'sport')
            <div class="app-chipbar" role="group" aria-label="Sport">
                @foreach ($sports->take(12) as $sport)
                    <a class="app-chip" href="{{ $app('sport', ['sport' => $sport['slug']]) }}">{{ $sport['name'] }}</a>
                @endforeach
            </div>
        @endif

        @php $lastGroup = null; $matchBannerShown = false; @endphp
        @forelse ($matches as $row)
            @php $group = $groupLabel($row['date'] ?? null); @endphp
            @if ($group !== $lastGroup)
                <div class="app-daygroup"><h3>{{ $group }}</h3></div>
                @php $lastGroup = $group; @endphp
            @endif
            <a class="app-match" href="{{ $app('match', ['match' => $row['slug']]) }}">
                <span class="app-when"><b>{{ $row['day'] }}</b><span>{{ $row['month'] }}</span></span>
                <span>
                    <strong>{{ $row['title'] }}</strong>
                    <em>{{ collect([$row['discipline'], $row['town'] ?: $row['province'], $kmAway($row)])->filter()->implode(' · ') }}</em>
                </span>
            </a>
            @if ($loop->iteration === 3)
                @include('mockups.partials.app-banners', ['banners' => $bannersFor('calendar')->take(1)])
                @php $matchBannerShown = true; @endphp
            @endif
        @empty
            <p class="app-empty"><strong>Nothing coming up</strong>No matches are listed here yet. When an organiser publishes a date, it shows up.</p>
        @endforelse
        @if (! $matchBannerShown)
            @include('mockups.partials.app-banners', ['banners' => $bannersFor('calendar')->take(1)])
        @endif
    </div>

@elseif ($screen === 'calendar')
    @php $grid = $phoneCalendar['grid']; @endphp
    <div class="app-pad">
        <div class="app-cal-nav">
            <a href="{{ $app('calendar', ['month' => $grid['prev'], 'day' => null]) }}" aria-label="Previous month"><x-mockups.icon name="chevron" style="transform: rotate(180deg);" /></a>
            <strong>{{ $grid['label'] }}</strong>
            <a href="{{ $app('calendar', ['month' => $grid['next'], 'day' => null]) }}" aria-label="Next month"><x-mockups.icon name="chevron" /></a>
        </div>
        <div class="app-cal" role="grid" aria-label="{{ $grid['label'] }}">
            @foreach (['M', 'T', 'W', 'T', 'F', 'S', 'S'] as $dow)
                <span class="app-cal-dow">{{ $dow }}</span>
            @endforeach
            @foreach ($grid['weeks'] as $week)
                @foreach ($week as $day)
                    @if ($day['in_month'])
                        <a href="{{ $app('calendar', ['month' => $grid['month'], 'day' => $day['date']]) }}" @class(['app-cal-day', 'today' => $day['is_today'], 'on' => $day['date'] === $phoneCalendar['day']])>
                            <b>{{ $day['day'] }}</b>
                            @if ($day['events'] !== [])
                                <span class="app-cal-dots">
                                    @foreach (array_slice($day['events'], 0, 3) as $event)
                                        <i data-family="{{ $event['family'] }}"></i>
                                    @endforeach
                                </span>
                            @endif
                        </a>
                    @else
                        <span class="app-cal-day off"><b>{{ $day['day'] }}</b></span>
                    @endif
                @endforeach
            @endforeach
        </div>
        @include('mockups.partials.app-banners', ['banners' => $bannersFor('calendar')->take(1)])
        <p class="app-sub">{{ $phoneCalendar['day_label'] }}</p>
        @forelse ($phoneCalendar['events'] as $row)
            <a class="app-match" href="{{ $app('match', ['match' => $row['slug']]) }}">
                <span class="app-when"><b>{{ $row['day'] }}</b><span>{{ $row['month'] }}</span></span>
                <span>
                    <strong>{{ $row['title'] }}</strong>
                    <em>{{ collect([$row['discipline'], $row['town'] ?: $row['province'], $kmAway($row)])->filter()->implode(' · ') }}</em>
                </span>
            </a>
        @empty
            <p class="app-empty">No matches on this day.</p>
        @endforelse
    </div>

@elseif ($screen === 'search')
    <div class="app-pad">
        @include('mockups.partials.app-event-search', [
            'q' => $eventSearch['q'],
            'scope' => $eventSearch['scope'],
            'dropped' => $eventSearch['dropped'],
        ])
        @if ($eventSearch['follows'] !== [])
            @php
                $flip = function (array $follow) use ($app, $eventSearch): string {
                    $dropped = $eventSearch['dropped'];
                    $dropped = $follow['on']
                        ? array_merge($dropped, [$follow['key']])
                        : array_values(array_diff($dropped, [$follow['key']]));

                    return $app('search', [
                        'q' => $eventSearch['q'],
                        'drop' => $dropped,
                        'scope' => $eventSearch['scope'] === 'all' ? 'all' : null,
                    ]);
                };
            @endphp
            <div class="app-follows">
                @foreach ($eventSearch['follows'] as $follow)
                    <a href="{{ $flip($follow) }}" @class(['app-follow', 'off' => ! $follow['on']])>{{ $follow['label'] }}</a>
                @endforeach
            </div>
        @endif
        @forelse ($eventSearch['results'] as $row)
            <a class="app-match" href="{{ $app('match', ['match' => $row['slug']]) }}">
                <span class="app-when"><b>{{ $row['day'] }}</b><span>{{ $row['month'] }}</span></span>
                <span>
                    <strong>{{ $row['title'] }}</strong>
                    <em>{{ collect([$row['discipline'], $row['town'] ?: $row['province'], $kmAway($row)])->filter()->implode(' · ') }}</em>
                </span>
            </a>
        @empty
            <p class="app-empty"><strong>No matches</strong>Nothing upcoming matches this search.</p>
        @endforelse
    </div>
@endif
