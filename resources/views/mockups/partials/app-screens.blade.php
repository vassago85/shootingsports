@php
    $ios = $platform === 'ios';
    $store = $ios ? 'Apple' : 'Google Play';
    $billedBy = $ios ? 'your Apple ID' : 'your Google Play account';
    $manageUrl = $ios
        ? 'https://apps.apple.com/account/subscriptions'
        : 'https://play.google.com/store/account/subscriptions';
    $manageLabel = $ios ? 'Manage in Apple Subscriptions' : 'Manage in Google Play';
    $length = $cycle === 'annual' ? '1 year' : '1 month';
    $plan = $pricing[$cycle];
    $freeFollows = config('plans.free.follows');
    $freeSearches = config('plans.free.saved_searches');
    $freeHistory = config('plans.free.history_months');
    $freeLog = config('plans.free.attended_events_slots');
    $place = $match
        ? collect([$match['range'], $match['town'], $match['province']])->filter()->implode(' · ')
        : '';
    $matchSponsor = $sponsors->first(function (array $sponsor) use ($match): bool {
        return $match !== null && array_intersect($sponsor['discipline_slugs'], $match['discipline_slugs']) !== [];
    }) ?? $sponsors->first();
    $kmAway = function (array $row): ?string {
        if (! is_numeric($row['distance_km'] ?? null)) {
            return null;
        }

        $km = (float) $row['distance_km'];

        return $km < 1 ? 'Under 1 km' : round($km).' km';
    };
@endphp

@if ($screen === 'today')
    <div class="app-pad">
        <p class="app-kicker">{{ $close['place'] ?? 'South Africa' }}</p>
        <h1 class="app-hero">Coming up</h1>
        <p class="app-lead">Matches on the register. Public listings stay free.</p>
        @include('mockups.partials.app-match-views')
        @include('mockups.partials.app-event-search')
        @if ($eventSearch['follows'] !== [])
            <p class="app-kicker">Following</p>
            <div class="app-follows">
                @foreach ($eventSearch['follows'] as $follow)
                    <a class="app-follow" href="{{ $app('search') }}">{{ $follow['label'] }}</a>
                @endforeach
            </div>
        @endif
        @include('mockups.partials.app-close-filter', ['closeScreen' => 'today'])
        @forelse ($matches as $row)
            <a class="app-match" href="{{ $app('match', ['match' => $row['slug']]) }}">
                <span class="app-when">
                    <b>{{ $row['day'] }}</b>
                    <span>{{ $row['month'] }}</span>
                </span>
                <span>
                    <strong>{{ $row['title'] }}</strong>
                    <em>{{ collect([$row['discipline'], $row['town'] ?: $row['province'], $kmAway($row)])->filter()->implode(' · ') }}</em>
                </span>
            </a>
            @if ($loop->iteration === 2)
                @include('mockups.partials.app-sponsor-strip')
            @endif
        @empty
            <p class="app-empty">
                @if (($close['gps'] ?? false) === true)
                    No matches within {{ $close['km'] }} km.
                @elseif (($close['province'] ?? null) !== null && ($close['on'] ?? false) === true)
                    No matches in {{ $close['place'] }} within {{ $close['km'] }} km.
                @elseif (($close['province'] ?? null) !== null)
                    No upcoming matches in {{ $close['place'] }}.
                @else
                    No upcoming matches are listed yet. When an organiser publishes a date, it shows up here.
                @endif
            </p>
            @include('mockups.partials.app-sponsor-strip')
        @endforelse
        @if ($matches->isNotEmpty() && $matches->count() < 2)
            @include('mockups.partials.app-sponsor-strip')
        @endif
    </div>

@elseif ($screen === 'calendar')
    @php
        $grid = $phoneCalendar['grid'];
    @endphp
    <div class="app-pad">
        <p class="app-kicker">{{ $close['place'] ?? 'South Africa' }}</p>
        <h1 class="app-hero">Calendar</h1>
        @include('mockups.partials.app-match-views')
        @include('mockups.partials.app-close-filter', ['closeScreen' => 'calendar'])
        <div class="app-cal-nav">
            <a href="{{ $app('calendar', ['month' => $grid['prev'], 'day' => null]) }}" aria-label="Previous month">‹</a>
            <strong>{{ $grid['label'] }}</strong>
            <a href="{{ $app('calendar', ['month' => $grid['next'], 'day' => null]) }}" aria-label="Next month">›</a>
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
        <h2 class="app-sub">{{ $phoneCalendar['day_label'] }}</h2>
        @forelse ($phoneCalendar['events'] as $row)
            <a class="app-match" href="{{ $app('match', ['match' => $row['slug']]) }}">
                <span class="app-when">
                    <b>{{ $row['day'] }}</b>
                    <span>{{ $row['month'] }}</span>
                </span>
                <span>
                    <strong>{{ $row['title'] }}</strong>
                    <em>{{ collect([$row['discipline'], $row['town'] ?: $row['province'], $kmAway($row)])->filter()->implode(' · ') }}</em>
                </span>
            </a>
        @empty
            <p class="app-empty">No matches on this day{{ ($close['place'] ?? null) ? ' in '.$close['place'] : '' }}.</p>
        @endforelse
    </div>

@elseif ($screen === 'search')
    <div class="app-pad">
        <h1 class="app-hero">Search</h1>
        <p class="app-lead">Upcoming matches. Your follows stay on this screen.</p>
        @include('mockups.partials.app-event-search', [
            'q' => $eventSearch['q'],
            'scope' => $eventSearch['scope'],
            'dropped' => $eventSearch['dropped'],
        ])
        @if ($eventSearch['follows'] === [])
            <p class="app-empty">This sample account is not following a sport, club or province yet.</p>
        @else
            @php
                $flip = function (array $follow) use ($app, $eventSearch): string {
                    $dropped = $eventSearch['dropped'];

                    if ($follow['on']) {
                        $dropped[] = $follow['key'];
                    } else {
                        $dropped = array_values(array_diff($dropped, [$follow['key']]));
                    }

                    return $app('search', [
                        'q' => $eventSearch['q'],
                        'drop' => $dropped,
                        'scope' => $eventSearch['scope'] === 'all' ? 'all' : null,
                    ]);
                };
            @endphp
            <p class="app-kicker">Following</p>
            <div class="app-follows">
                @foreach ($eventSearch['follows'] as $follow)
                    <a href="{{ $flip($follow) }}" @class(['app-follow', 'off' => ! $follow['on']])>{{ $follow['label'] }}</a>
                @endforeach
            </div>
            @if ($eventSearch['scope'] === 'all')
                <a class="app-textlink" href="{{ $app('search', ['q' => $eventSearch['q'], 'drop' => $eventSearch['dropped']]) }}">Only what I follow</a>
            @else
                <a class="app-textlink" href="{{ $app('search', ['q' => $eventSearch['q'], 'drop' => $eventSearch['dropped'], 'scope' => 'all']) }}">Search all matches</a>
            @endif
        @endif

        @include('mockups.partials.app-close-filter', [
            'closeScreen' => 'search',
            'closeExtra' => [
                'q' => $eventSearch['q'],
                'drop' => $eventSearch['dropped'],
                'scope' => $eventSearch['scope'] === 'all' ? 'all' : null,
            ],
        ])

        @forelse ($eventSearch['results'] as $row)
            <a class="app-match" href="{{ $app('match', ['match' => $row['slug']]) }}">
                <span class="app-when">
                    <b>{{ $row['day'] }}</b>
                    <span>{{ $row['month'] }}</span>
                </span>
                <span>
                    <strong>{{ $row['title'] }}</strong>
                    <em>{{ collect([$row['discipline'], $row['organiser'], $row['town'] ?: $row['province'], $kmAway($row)])->filter()->implode(' · ') }}</em>
                </span>
            </a>
        @empty
            <p class="app-empty">
                @if (($close['gps'] ?? false) === true)
                    No upcoming matches within {{ $close['km'] }} km match this search.
                @elseif (($close['province'] ?? null) !== null && ($close['on'] ?? false) === true)
                    No upcoming matches in {{ $close['place'] }} within {{ $close['km'] }} km match this search.
                @elseif (($close['province'] ?? null) !== null)
                    No upcoming matches in {{ $close['place'] }} match this search.
                @else
                    No upcoming matches match this search.
                @endif
            </p>
        @endforelse
    </div>

@elseif ($screen === 'match')
    <div class="app-pad">
        @if ($saved)
            <p class="app-banner">Saved on this phone. This preview does not keep it.</p>
        @endif
        @if ($match)
            <p class="app-kicker">{{ $match['dow'] }} {{ $match['date_label'] }}@if ($match['time']) · {{ $match['time'] }}@endif</p>
            <h1 class="app-hero">{{ $match['title'] }}</h1>
            <p class="app-lead">{{ $place !== '' ? $place : 'Venue not listed yet.' }}</p>
            <dl class="app-facts">
                @if ($match['discipline'])<div><dt>Sport</dt><dd>{{ $match['discipline'] }}</dd></div>@endif
                @if ($match['organiser'])<div><dt>Organiser</dt><dd>{{ $match['organiser'] }}</dd></div>@endif
                @if ($match['level'])<div><dt>Level</dt><dd>{{ $match['level'] }}</dd></div>@endif
                @if ($match['fee'])<div><dt>Entry</dt><dd>{{ $match['fee'] }}</dd></div>@endif
                @if ($match['distance'])<div><dt>Distance</dt><dd>{{ $match['distance'] }}</dd></div>@endif
            </dl>
            <div class="app-stack">
                <a class="app-btn" href="{{ $app('match', ['match' => $match['slug'], 'saved' => 1]) }}">Save match</a>
                <a class="app-btn secondary" href="{{ $app('pack', ['match' => $match['slug']]) }}">Pack for this match</a>
                @if (filled($match['directions']))
                    <a class="app-btn secondary" href="{{ $match['directions'] }}" target="_blank" rel="noopener">Directions</a>
                @endif
                @if (filled($match['entry_url']))
                    <a class="app-btn quiet" href="{{ $match['entry_url'] }}" target="_blank" rel="noopener">Entry details from the organiser</a>
                @endif
            </div>
            @if ($matchSponsor)
                <p class="app-kicker">Sponsored</p>
                @include('mockups.partials.app-sponsor', ['sponsor' => $matchSponsor])
            @endif
        @else
            <h1 class="app-hero">No match open</h1>
            <p class="app-lead">Nothing upcoming is on the register in this preview.</p>
            <a class="app-btn" href="{{ $app('today') }}">Back to today</a>
        @endif
    </div>

@elseif ($screen === 'find')
    <div class="app-pad">
        <h1 class="app-hero">Find</h1>
        <p class="app-lead">Clubs, ranges and sports. Suppliers have their own tab.</p>
        <div class="app-group">
            <a href="{{ $app('clubs') }}">
                <span class="app-mark"><x-mockups.icon name="users" /></span>
                <span class="app-group-copy"><span>Clubs &amp; series</span><small>{{ $stats['clubs'] }} on the register</small></span>
            </a>
            <a href="{{ $app('ranges') }}">
                <span class="app-mark"><x-mockups.icon name="range" /></span>
                <span class="app-group-copy"><span>Ranges</span><small>{{ $stats['ranges'] }} on the register</small></span>
            </a>
            <a href="{{ $app('sports') }}">
                <span class="app-mark"><x-mockups.icon name="target" /></span>
                <span class="app-group-copy"><span>Sports</span><small>{{ $stats['sports'] }} on the register</small></span>
            </a>
        </div>
    </div>

@elseif ($screen === 'clubs')
    <div class="app-pad">
        <h1 class="app-hero">Clubs &amp; series</h1>
        <p class="app-lead">{{ ($close['anywhere'] ?? false) === true ? 'Membership clubs and match series across South Africa.' : 'Membership clubs and match series in '.($close['place'] ?? 'your province').'.' }}</p>
        @include('mockups.partials.app-close-filter', ['closeScreen' => 'clubs'])
        @forelse ($clubs->take(12) as $row)
            @php
                $clubIcon = match ($row['type'] ?? '') {
                    'Series' => 'flag',
                    'Association' => 'badge',
                    default => 'users',
                };
            @endphp
            <a class="app-row" href="{{ $app('club', ['club' => $row['slug']]) }}">
                <span class="app-row-main">
                    <span class="app-mark"><x-mockups.icon :name="$clubIcon" /></span>
                    <span>
                        <strong>{{ $row['name'] }}</strong>
                        <em>{{ collect([$row['type'], $row['place'], $kmAway($row)])->filter()->implode(' · ') }}</em>
                    </span>
                </span>
            </a>
        @empty
            <p class="app-empty">{{ ($close['province'] ?? null) !== null ? 'No clubs or series in '.$close['place'].'.' : 'No clubs or series are published yet.' }}</p>
        @endforelse
    </div>

@elseif ($screen === 'club')
    <div class="app-pad">
        @if ($club)
            <h1 class="app-hero">{{ $club['name'] }}</h1>
            <p class="app-lead">{{ $club['place'] ?: 'Place not listed.' }}</p>
            <dl class="app-facts">
                @if ($club['type'])<div><dt>Type</dt><dd>{{ $club['type'] }}</dd></div>@endif
                <div><dt>Upcoming</dt><dd>{{ $club['upcoming_count'] }}</dd></div>
            </dl>
            @if ($club['disciplines'] !== [])
                <p class="app-lead">{{ implode(', ', $club['disciplines']) }}</p>
            @endif
        @else
            <h1 class="app-hero">No club open</h1>
            <a class="app-btn" href="{{ $app('clubs') }}">All clubs</a>
        @endif
    </div>

@elseif ($screen === 'ranges')
    <div class="app-pad">
        <h1 class="app-hero">Ranges</h1>
        @include('mockups.partials.app-close-filter', ['closeScreen' => 'ranges'])
        @forelse ($ranges->take(12) as $row)
            <a class="app-row" href="{{ $app('range', ['range' => $row['slug']]) }}">
                <span class="app-row-main">
                    <span class="app-mark"><x-mockups.icon name="range" /></span>
                    <span>
                        <strong>{{ $row['name'] }}</strong>
                        <em>{{ collect([$row['place'], $row['max_distance'], $kmAway($row)])->filter()->implode(' · ') }}</em>
                    </span>
                </span>
            </a>
        @empty
            <p class="app-empty">{{ ($close['province'] ?? null) !== null ? 'No ranges in '.$close['place'].'.' : 'No ranges are published yet.' }}</p>
        @endforelse
    </div>

@elseif ($screen === 'range')
    <div class="app-pad">
        @if ($range)
            <h1 class="app-hero">{{ $range['name'] }}</h1>
            <p class="app-lead">{{ $range['place'] ?: 'Place not listed.' }}</p>
            <dl class="app-facts">
                @if ($range['max_distance'])<div><dt>Distance</dt><dd>{{ $range['max_distance'] }}</dd></div>@endif
                @if ($range['access'])<div><dt>Access</dt><dd>{{ $range['access'] }}</dd></div>@endif
            </dl>
            @if (filled($range['directions']))
                <a class="app-btn secondary" href="{{ $range['directions'] }}" target="_blank" rel="noopener">Directions</a>
            @endif
        @else
            <h1 class="app-hero">No range open</h1>
            <a class="app-btn" href="{{ $app('ranges') }}">All ranges</a>
        @endif
    </div>

@elseif ($screen === 'sports')
    @php
        $sportFamilies = [
            'rifle' => 'Rifle',
            'handgun' => 'Handgun',
            'shotgun' => 'Shotgun',
            'airgun' => 'Airgun',
            'multi' => 'Multi-gun',
        ];
        $family = request()->string('family')->toString();
        $family = array_key_exists($family, $sportFamilies) ? $family : '';
        $sportQuery = trim(request()->string('q')->toString());
        $sportRows = $sports->filter(function (array $sport) use ($family, $sportQuery): bool {
            if ($family !== '' && $sport['family'] !== $family) {
                return false;
            }

            if ($sportQuery === '') {
                return true;
            }

            return str_contains(mb_strtolower($sport['name'].' '.($sport['blurb'] ?? '')), mb_strtolower($sportQuery));
        })->values();
        $presentFamilies = $sports->pluck('family')->filter(fn (mixed $value): bool => is_string($value) && array_key_exists($value, $sportFamilies))->unique()->all();
    @endphp
    <div class="app-pad">
        <h1 class="app-hero">Sports</h1>
        <form class="app-search-form" method="get" action="{{ $mk('mockups.apps') }}">
            <input type="hidden" name="screen" value="sports">
            <input type="hidden" name="theme" value="{{ request()->query('theme') === 'light' ? 'light' : 'dark' }}">
            @if (request()->query('type') === 'large')
                <input type="hidden" name="type" value="large">
            @endif
            @if (request()->query('device') === 'mobile')
                <input type="hidden" name="device" value="mobile">
            @endif
            @if ($family !== '')
                <input type="hidden" name="family" value="{{ $family }}">
            @endif
            @if (request()->filled('home'))
                <input type="hidden" name="home" value="{{ request()->string('home')->toString() }}">
            @endif
            @if (request()->query('province') === 'all' || \App\Enums\Province::tryFrom(request()->string('province')->toString()) instanceof \App\Enums\Province)
                <input type="hidden" name="province" value="{{ request()->string('province')->toString() }}">
            @endif
            <label class="app-search-field">
                <x-mockups.icon name="search" />
                <input type="search" name="q" value="{{ $sportQuery }}" placeholder="Rifle, IPSC, trap" aria-label="Search sports">
            </label>
        </form>
        <div class="app-chips" role="group" aria-label="Sport category">
            <a href="{{ $app('sports', ['family' => null, 'q' => $sportQuery !== '' ? $sportQuery : null]) }}" @class(['on' => $family === ''])>All</a>
            @foreach ($sportFamilies as $value => $label)
                @if (in_array($value, $presentFamilies, true))
                    <a href="{{ $app('sports', ['family' => $value, 'q' => $sportQuery !== '' ? $sportQuery : null]) }}" @class(['on' => $family === $value])>{{ $label }}</a>
                @endif
            @endforeach
        </div>
        @forelse ($sportRows as $row)
            <a class="app-row" href="{{ $app('sport', ['sport' => $row['slug'], 'family' => $family !== '' ? $family : null, 'q' => $sportQuery !== '' ? $sportQuery : null]) }}">
                <span class="app-row-main">
                    <span class="app-mark"><x-mockups.icon name="target" /></span>
                    <span>
                        <strong>{{ $row['name'] }}</strong>
                        <em>{{ collect([$row['family_label'], $row['upcoming_count'].' upcoming'])->filter()->implode(' · ') }}</em>
                    </span>
                </span>
            </a>
        @empty
            <p class="app-empty">{{ $family !== '' || $sportQuery !== '' ? 'No sports match that filter.' : 'No sports are published yet.' }}</p>
            @if ($family !== '' || $sportQuery !== '')
                <a class="app-btn secondary" href="{{ $app('sports', ['family' => null, 'q' => null]) }}">Show all sports</a>
            @endif
        @endforelse
    </div>

@elseif ($screen === 'sport')
    <div class="app-pad">
        @if ($sport)
            <p class="app-kicker">{{ $sport['family_label'] }}</p>
            <h1 class="app-hero">{{ $sport['name'] }}</h1>
            <p class="app-lead">{{ $sport['blurb'] ?: 'A longer explanation has not been written for this sport yet.' }}</p>
            <h2 class="app-sub">Upcoming</h2>
            @include('mockups.partials.app-close-filter', [
                'closeScreen' => 'sport',
                'closeExtra' => ['sport' => $sport['slug']],
            ])
            @forelse ($sportMatches as $row)
                <a class="app-match" href="{{ $app('match', ['match' => $row['slug']]) }}">
                    <span class="app-when">
                        <b>{{ $row['day'] }}</b>
                        <span>{{ $row['month'] }}</span>
                    </span>
                    <span>
                        <strong>{{ $row['title'] }}</strong>
                        <em>{{ collect([$row['town'] ?: $row['province'], $row['organiser'], $kmAway($row)])->filter()->implode(' · ') }}</em>
                    </span>
                </a>
            @empty
                <p class="app-empty">
                    @if (($close['gps'] ?? false) === true)
                        No upcoming {{ $sport['name'] }} matches within {{ $close['km'] }} km.
                    @elseif (($close['province'] ?? null) !== null && ($close['on'] ?? false) === true)
                        No upcoming {{ $sport['name'] }} matches in {{ $close['place'] }} within {{ $close['km'] }} km.
                    @elseif (($close['province'] ?? null) !== null)
                        No upcoming {{ $sport['name'] }} matches in {{ $close['place'] }}.
                    @else
                        No upcoming {{ $sport['name'] }} matches are listed yet.
                    @endif
                </p>
            @endforelse
            <a class="app-btn secondary" href="{{ $app('pack', ['sport' => $sport['slug']]) }}">What to pack</a>
        @else
            <h1 class="app-hero">No sport open</h1>
            <a class="app-btn" href="{{ $app('sports') }}">All sports</a>
        @endif
    </div>

@elseif ($screen === 'pack')
    <div class="app-pad">
        @if ($packing)
            @if ($packing['match'])
                @php
                    $toggle = function (string $key) use ($app, $packing): string {
                        $on = $packing['on'];

                        if (in_array($key, $on, true)) {
                            $on = array_values(array_diff($on, [$key]));
                        } else {
                            $on[] = $key;
                        }

                        return $app('pack', [
                            'match' => $packing['match']['slug'],
                            'on' => $on,
                            'added' => $packing['added'],
                        ]);
                    };
                    $basics = array_values(array_filter($packing['rows'], fn (array $row): bool => ! $row['custom']));
                    $extras = array_values(array_filter($packing['rows'], fn (array $row): bool => $row['custom']));
                @endphp
                <p class="app-kicker">{{ $packing['sport'] ?: 'Match' }}</p>
                <h1 class="app-hero">{{ $packing['match']['title'] }}</h1>
                @if ($packing['has_sport'])
                    <p class="app-lead">Basics come with {{ $packing['sport'] }}. Add anything this match needs.</p>
                @else
                    <p class="app-lead">This match has no sport yet, so there is no starter list. Add what you are taking.</p>
                @endif

                @if ($basics !== [])
                    <h2 class="app-sub">Basics</h2>
                    <div class="app-checks">
                        @foreach ($basics as $row)
                            <a class="app-check {{ $row['on'] ? 'on' : '' }}" href="{{ $toggle($row['key']) }}">
                                <span class="app-box" aria-hidden="true"></span>
                                <span>{{ $row['label'] }}</span>
                            </a>
                        @endforeach
                    </div>
                @endif

                <h2 class="app-sub">Added for this match</h2>
                @if ($extras !== [])
                    <div class="app-checks">
                        @foreach ($extras as $row)
                            <a class="app-check {{ $row['on'] ? 'on' : '' }}" href="{{ $toggle($row['key']) }}">
                                <span class="app-box" aria-hidden="true"></span>
                                <span>{{ $row['label'] }}</span>
                            </a>
                        @endforeach
                    </div>
                @else
                    <p class="app-empty">Nothing extra yet.</p>
                @endif

                <form class="app-add" method="get" action="{{ $mk('mockups.apps') }}">
                    <input type="hidden" name="screen" value="pack">
                    <input type="hidden" name="match" value="{{ $packing['match']['slug'] }}">
                    @if (request()->query('device') === 'mobile')
                        <input type="hidden" name="device" value="mobile">
                    @endif
                    @foreach ($packing['on'] as $key)
                        <input type="hidden" name="on[]" value="{{ $key }}">
                    @endforeach
                    @foreach ($packing['added'] as $label)
                        <input type="hidden" name="added[]" value="{{ $label }}">
                    @endforeach
                    <label>
                        <span class="app-kicker">Add an item</span>
                        <input name="item" maxlength="80" placeholder="Spare battery, lunch, dope card" aria-label="Add an item">
                    </label>
                    <button type="submit">Add</button>
                </form>
                <p class="app-fine">Ticks and extra items stay in this preview link. The app would keep them on the phone.</p>
            @else
                <h1 class="app-hero">That match is not listed</h1>
                <p class="app-lead">It is not on the upcoming register, so there is nothing to pack.</p>
                <a class="app-btn" href="{{ $app('today') }}">Upcoming matches</a>
            @endif
        @elseif ($sportMissing)
            <h1 class="app-hero">That sport is not on the register.</h1>
            <a class="app-btn" href="{{ $app('pack') }}">All packing lists</a>
        @elseif ($kit)
            @include('mockups.partials.app-kit')
        @else
            <h1 class="app-hero">Packing lists</h1>
            <p class="app-lead">Each sport starts with the basics. Open a match to tick them off and add what you are taking that day.</p>
            @forelse ($kits->groupBy('family') as $family => $group)
                <h2 class="app-sub">{{ $family }}</h2>
                <div class="app-group">
                    @foreach ($group as $row)
                        <a href="{{ $app('pack', ['sport' => $row['slug']]) }}">
                            <span class="app-mark"><x-mockups.icon name="bag" /></span>
                            <span class="app-group-copy"><span>{{ $row['name'] }}</span><small>{{ $row['count'] }} basics</small></span>
                        </a>
                    @endforeach
                </div>
            @empty
                <p class="app-empty">No sports are published yet.</p>
            @endforelse
        @endif
    </div>

@elseif ($screen === 'suppliers')
    <div class="app-pad">
        <h1 class="app-hero">Suppliers</h1>
        <p class="app-lead">Dealers, gunsmiths and the rest of the industry. Sponsored ads are labelled. They are not mixed into the list order.</p>
        @if ($sponsors->isNotEmpty())
            <p class="app-kicker">Sponsored</p>
            @foreach ($sponsors as $sponsor)
                @include('mockups.partials.app-sponsor')
            @endforeach
        @endif
        <h2 class="app-sub">All suppliers</h2>
        @include('mockups.partials.app-close-filter', ['closeScreen' => 'suppliers'])
        @forelse ($businesses->take(20) as $row)
            <a class="app-row" href="{{ $app('supplier', ['supplier' => $row['slug']]) }}">
                <span class="app-row-main">
                    <span class="app-mark"><x-mockups.icon name="store" /></span>
                    <span>
                        <strong>{{ $row['name'] }}</strong>
                        <em>{{ collect([$row['category'], $row['place']])->filter()->implode(' · ') }}</em>
                    </span>
                </span>
            </a>
        @empty
            <p class="app-empty">{{ ($close['province'] ?? null) !== null ? 'No suppliers in '.$close['place'].'.' : 'No suppliers are published yet.' }}</p>
        @endforelse
    </div>

@elseif ($screen === 'supplier')
    <div class="app-pad">
        @if ($supplier)
            @if (! $supplier['public'])
                <p class="app-kicker">Advertising account</p>
                <h1 class="app-hero">{{ $supplier['name'] }}</h1>
                <p class="app-lead">Distributors are not a public listing. This account is for advertising.</p>
                @if ($supplier['website'])
                    <a class="app-btn" href="{{ $supplier['website'] }}" target="_blank" rel="noopener">Website</a>
                @endif
            @else
            @if ($supplier['featured'])<p class="app-kicker">Sponsored</p>@endif
            <h1 class="app-hero">{{ $supplier['name'] }}</h1>
            <p class="app-lead">{{ collect([$supplier['category'], $supplier['place']])->filter()->implode(' · ') }}</p>
            @if ($supplier['verified'])<p class="app-banner">Verified on the register.</p>@endif
            <p class="app-lead">{{ $supplier['description'] ?: 'No description has been published.' }}</p>
            @if ($supplier['services'] !== [])
                <h2 class="app-sub">Services</h2>
                <ul class="app-list plain">
                    @foreach ($supplier['services'] as $service)
                        <li>{{ $service }}</li>
                    @endforeach
                </ul>
            @endif
            <div class="app-stack">
                @if ($supplier['website'])<a class="app-btn" href="{{ $supplier['website'] }}" target="_blank" rel="noopener">Website</a>@endif
                @if ($supplier['phone'])<a class="app-btn secondary" href="tel:{{ $supplier['phone'] }}">Call</a>@endif
                @if ($supplier['email'])<a class="app-btn secondary" href="mailto:{{ $supplier['email'] }}">Email</a>@endif
            </div>
            @endif
        @else
            <h1 class="app-hero">No supplier open</h1>
            <a class="app-btn" href="{{ $app('suppliers') }}">All suppliers</a>
        @endif
    </div>

@elseif ($screen === 'you')
    <div class="app-pad">
        <p class="app-kicker">Sample account</p>
        <h1 class="app-hero">You</h1>
        <p class="app-lead">Matches, clubs, series and ranges open in {{ $close['home_place'] }}. You can still look further from the list.</p>
        <h2 class="app-sub">Where you shoot</h2>
        <div class="app-chips" role="group" aria-label="Account location">
            @foreach (\App\Enums\Province::cases() as $province)
                <a href="{{ $app('you', ['home' => $province->value, 'province' => null]) }}" @class(['on' => $close['home'] === $province->value])>{{ $province->getLabel() }}</a>
            @endforeach
        </div>

        <a class="app-plan" href="{{ $app('subscription') }}">
            <span class="app-kicker">Pro · active</span>
            <strong>{{ $pricing['monthly']['display'] }}</strong>
            <em>Renews {{ $renews }}. Cancel in one step.</em>
        </a>
        <a class="app-btn" href="{{ $app('cancel') }}">Cancel subscription</a>

        <div class="app-group">
            <a href="{{ $app('pack') }}"><span class="app-mark"><x-mockups.icon name="bag" /></span><span class="app-group-copy"><span>Packing lists</span><small>Basics for each sport</small></span></a>
            <a href="{{ $app('alerts') }}"><span class="app-mark"><x-mockups.icon name="bell" /></span><span class="app-group-copy"><span>Alerts</span><small>Unsubscribe from emails</small></span></a>
            <a href="{{ $app('data') }}"><span class="app-mark"><x-mockups.icon name="shield" /></span><span class="app-group-copy"><span>Your data</span><small>What we store, download, delete</small></span></a>
            <a href="{{ $app('plans') }}"><span class="app-mark"><x-mockups.icon name="card" /></span><span class="app-group-copy"><span>Plans and prices</span><small>See the cost before you pay</small></span></a>
            <a href="{{ route('privacy') }}"><span class="app-mark"><x-mockups.icon name="lock" /></span><span class="app-group-copy"><span>Privacy policy</span><small>POPIA notice</small></span></a>
            <a href="{{ route('terms') }}"><span class="app-mark"><x-mockups.icon name="file" /></span><span class="app-group-copy"><span>Terms of use</span></span></a>
            <a href="{{ $app('permissions') }}"><span class="app-mark"><x-mockups.icon name="pin" /></span><span class="app-group-copy"><span>Location and alerts</span><small>Home province is set above. A pin is optional.</small></span></a>
            <a href="{{ $app('signin') }}"><span class="app-mark"><x-mockups.icon name="logout" /></span><span class="app-group-copy"><span>Sign out</span></span></a>
        </div>

        <a class="app-btn quiet danger" href="{{ $app('delete') }}">Delete account</a>
    </div>

@elseif ($screen === 'alerts')
    <div class="app-pad">
        <h1 class="app-hero">Alerts</h1>
        @if ($paused)
            <p class="app-banner">You are unsubscribed from optional emails. Match alerts, the weekly digest, product updates and trial reminders are off.</p>
            <p class="app-lead">Account emails still arrive: password resets, receipts and enquiry replies. The account does not work without those.</p>
            <a class="app-btn secondary" href="{{ $app('alerts', ['emails' => 'on']) }}">Turn optional emails back on</a>
        @else
            <p class="app-lead">One tap stops every optional email. You do not have to hunt through settings.</p>
            <a class="app-btn" href="{{ $app('alerts', ['emails' => 'off']) }}">Unsubscribe from all optional emails</a>
        @endif

        <ul class="app-list">
            <li><span>New match alerts</span><b>{{ $paused ? 'Off' : 'On' }}</b><small>When a club or sport you follow adds a match.</small></li>
            <li><span>Weekly digest</span><b>Off</b><small>Monday summary. Off unless you turn it on.</small></li>
            <li><span>Product updates</span><b>{{ $paused ? 'Off' : 'On' }}</b><small>Rare notes about the register. Not a newsletter.</small></li>
            <li><span>Trial reminders</span><b>{{ $paused ? 'Off' : 'On' }}</b><small>Two emails during a 30-day trial. Then they stop.</small></li>
            <li><span>Account emails</span><b>Always on</b><small>Receipts, password resets, enquiry replies.</small></li>
            <li><span>Push on this phone</span><b>Off</b><small>Stays off until you allow it under Near me.</small></li>
        </ul>
    </div>

@elseif ($screen === 'subscription')
    <div class="app-pad">
        @if ($started)
            <p class="app-banner">Trial started. Cancel before {{ $renews }} and you pay nothing.</p>
        @endif
        @if ($restored)
            <p class="app-banner">Purchases restored. Pro is on this {{ $ios ? 'Apple ID' : 'Google account' }}. We never see your card.</p>
        @endif
        <p class="app-kicker">ShootingSports Pro</p>
        <h1 class="app-hero">{{ $pricing['monthly']['display'] }}</h1>
        <p class="app-lead">Renews {{ $renews }}. Billed by {{ $billedBy }}. Cancel any time before that and the next charge does not happen.</p>
        <a class="app-btn" href="{{ $app('cancel') }}">Cancel subscription</a>
        <div class="app-stack">
            <a class="app-btn secondary" href="{{ $app('plans') }}">Change plan</a>
            <a class="app-btn secondary" href="{{ $app('subscription', ['restored' => 1]) }}">Restore purchases</a>
            <a class="app-btn quiet" href="{{ $manageUrl }}" target="_blank" rel="noopener">{{ $manageLabel }}</a>
        </div>
        <p class="app-fine">Cancelling here stops future charges. Pro stays on until {{ $renews }}, the end of the time already paid for. Deleting the app does not cancel the subscription.</p>
        <p class="app-fine"><a href="{{ route('privacy') }}">Privacy policy</a> · <a href="{{ route('terms') }}">Terms of use</a></p>
    </div>

@elseif ($screen === 'cancel')
    <div class="app-pad">
        <h1 class="app-hero">Cancel Pro?</h1>
        <ul class="app-list plain">
            <li>Pro stays on until {{ $renews }}.</li>
            <li>You will not be charged again.</li>
            <li>Your follows, saved searches and shooting log stay on the account.</li>
            <li>After {{ $renews }} the free limits apply: {{ $freeFollows }} follows, {{ $freeSearches }} saved search, {{ $freeHistory }} months of history, {{ $freeLog }} log entries.</li>
        </ul>
        <div class="app-stack">
            <a class="app-btn" href="{{ $app('cancelled') }}">Cancel subscription</a>
            <a class="app-btn secondary" href="{{ $app('subscription') }}">Keep Pro</a>
        </div>
    </div>

@elseif ($screen === 'cancelled')
    <div class="app-pad">
        <p class="app-kicker">Done</p>
        <h1 class="app-hero">You're cancelled</h1>
        <p class="app-lead">Pro stays on until {{ $renews }}. No more charges. We do not ask you to confirm again.</p>
        <div class="app-stack">
            <a class="app-btn" href="{{ $app('you') }}">Back to You</a>
            <a class="app-btn secondary" href="{{ $app('plans') }}">See plans again</a>
        </div>
    </div>

@elseif ($screen === 'plans')
    <div class="app-pad">
        <p class="app-kicker">ShootingSports Pro</p>
        <h1 class="app-hero">{{ $plan['display'] }}</h1>
        <p class="app-lead">{{ $trialDays }} days free, then this price. Cancel before the trial ends and you pay nothing.</p>
        <div class="app-cycles">
            <a href="{{ $app('plans', ['cycle' => 'annual']) }}" @class(['on' => $cycle === 'annual'])>
                <b>Annual</b>
                <strong>{{ $pricing['annual']['display'] }}</strong>
                <em>{{ $pricing['annual']['summary'] }}</em>
            </a>
            <a href="{{ $app('plans', ['cycle' => 'monthly']) }}" @class(['on' => $cycle === 'monthly'])>
                <b>Monthly</b>
                <strong>{{ $pricing['monthly']['display'] }}</strong>
                <em>{{ $pricing['monthly']['summary'] }}</em>
            </a>
        </div>
        <dl class="app-facts">
            <div><dt>Service</dt><dd>ShootingSports Pro</dd></div>
            <div><dt>Length</dt><dd>{{ $length }}, auto-renewable</dd></div>
            <div><dt>Price</dt><dd>{{ $plan['display'] }}</dd></div>
            <div><dt>Trial</dt><dd>{{ $trialDays }} days free, then the price above</dd></div>
            <div><dt>Charged to</dt><dd>{{ ucfirst($billedBy) }}</dd></div>
        </dl>
        <ul class="app-list plain">
            <li>Unlimited follows, saved searches and shooting log.</li>
            <li>Printable attendance record and CSV, for dedicated-status renewals.</li>
            <li>Up to {{ config('plans.pro.household_profiles') }} shooters in one household.</li>
        </ul>
        <div class="app-legal">
            <p>Payment is charged to {{ $billedBy }} when you confirm.</p>
            <p>The plan renews on its own unless you cancel at least 24 hours before the period ends. The renewal charge can happen in the 24 hours before then.</p>
            <p>Cancel in the app, or in {{ $store }} subscriptions. If you buy a plan during the trial, the unused free days are forfeited.</p>
            <p>On the website, the {{ $trialDays }}-day trial does not take a card and does not auto-bill. In the App Store and on Google Play, the trial is billed by the store and becomes a paid plan unless you cancel first.</p>
            <p><a href="{{ route('privacy') }}">Privacy policy</a> · <a href="{{ route('terms') }}">Terms of use</a></p>
        </div>
        <div class="app-stack">
            <a class="app-btn" href="{{ $app('subscription', ['started' => 1]) }}">Start {{ $trialDays }}-day free trial</a>
            <a class="app-btn secondary" href="{{ $app('subscription', ['restored' => 1]) }}">Restore purchases</a>
        </div>
    </div>

@elseif ($screen === 'data')
    <div class="app-pad">
        <h1 class="app-hero">Your data</h1>
        <p class="app-lead">We do not sell personal information. Here is every private thing the app holds, why, and how you take it back.</p>
        <div class="app-stack">
            <a class="app-btn" href="{{ $app('data', ['saved' => 1]) }}">Download my data</a>
            <a class="app-btn secondary" href="{{ $app('delete') }}">Delete account</a>
        </div>
        @if ($saved)
            <p class="app-banner">In the real app this sends a file of your account, follows, searches and log. This preview does not build the file.</p>
        @endif

        <h2 class="app-sub">What we store</h2>
        <ul class="app-list">
            <li><span>Account</span><small>Name, email and a hashed password, so you can sign in.</small></li>
            <li><span>Follows</span><small>Sports, clubs and a province, so the match list matches you. Free accounts: {{ $freeFollows }} follows.</small></li>
            <li><span>Saved searches</span><small>Free: {{ $freeSearches }}. Pro: no cap.</small></li>
            <li><span>Shooting log</span><small>Matches you mark as shot. Free: {{ $freeLog }} entries. Pro: full history, plus a PDF and CSV.</small></li>
            <li><span>Alert choices</span><small>Which emails you want. A push token only if you allow alerts on this phone.</small></li>
            <li><span>Subscription</span><small>Whether Pro is active, and the renewal date, from {{ $store }}. Card numbers stay with the store.</small></li>
            <li><span>Location</span><small>The province on your account, so lists open there. A one-time pin only if you tap Close to me. That pin is not stored.</small></li>
        </ul>

        <h2 class="app-sub">What we don't collect</h2>
        <ul class="app-list plain">
            <li>Advertising ID, contacts, photos or microphone.</li>
            <li>A list of firearms you own.</li>
            <li>Browsing sold to advertisers. Screen counts stay on our servers and are not tied to your name.</li>
        </ul>

        <h2 class="app-sub">Who can see it</h2>
        <ul class="app-list plain">
            <li>You.</li>
            <li>ShootingSports, to run the register.</li>
            <li>{{ $store }}, for the subscription only.</li>
            <li>The email sender, only for messages you still have switched on.</li>
            <li>No one else. No advertising network.</li>
        </ul>

        <h2 class="app-sub">How long, and your rights</h2>
        <p class="app-lead">Private rows stay while the account exists. Delete the account and they are erased. A public club or match listing belongs to that organiser. Ask for a listing change at <a href="mailto:hello@shootingsports.co.za">hello@shootingsports.co.za</a>.</p>
        <p class="app-lead">Under POPIA you can ask for access, a correction or deletion from this screen, or by email to the same address.</p>
        <p class="app-lead">The app is for adults, 18 and over. It is a match register. It does not sell firearms. Traffic is sent over HTTPS. Passwords are stored hashed.</p>
        <p class="app-fine"><a href="{{ route('privacy') }}">Privacy policy</a> · <a href="{{ route('terms') }}">Terms of use</a></p>
    </div>

@elseif ($screen === 'delete')
    <div class="app-pad">
        <h1 class="app-hero">Delete your account?</h1>
        <p class="app-lead">This removes the private account. It cannot be undone from the app.</p>
        <ul class="app-list plain">
            <li>Name, email and password hash.</li>
            <li>Follows, saved searches and the shooting log.</li>
            <li>Alert settings and this phone's push token.</li>
        </ul>
        <div class="app-legal">
            <p>Deleting the account does not cancel a store subscription. {{ $store }} can keep charging until you cancel. Cancel Pro first.</p>
        </div>
        <div class="app-stack">
            <a class="app-btn" href="{{ $app('cancel') }}">Cancel subscription first</a>
            <a class="app-btn secondary" href="{{ $app('data') }}">Download my data</a>
            <a class="app-btn danger" href="{{ $app('deleted') }}">Delete my account</a>
            <a class="app-btn quiet" href="{{ $app('you') }}">Keep my account</a>
        </div>
        <p class="app-fine">You can also write to <a href="mailto:hello@shootingsports.co.za">hello@shootingsports.co.za</a> and ask for the account to be deleted. That is the same request, from outside the app.</p>
    </div>

@elseif ($screen === 'deleted')
    <div class="app-pad">
        <p class="app-kicker">Preview</p>
        <h1 class="app-hero">Account deleted</h1>
        <p class="app-lead">The private account would be gone: profile, follows, searches, log and alert settings. Public match listings a club published stay on the register.</p>
        <p class="app-lead">If a store subscription was still active, cancel it in {{ $store }} so the charges stop.</p>
        <a class="app-btn" href="{{ $app('signin') }}">Done</a>
    </div>

@elseif ($screen === 'permissions')
    <div class="app-pad">
        <h1 class="app-hero">Near me</h1>
        <p class="app-lead">Location is used once, to show matches close to you. We don't save where you have been. You can say no and still open every public match.</p>
        <div class="app-stack">
            <a class="app-btn" href="{{ $app('today', ['near' => 'denied']) }}" data-app-near="{{ $app('today', ['near' => '1', 'km' => 100]) }}">Use my location once</a>
            <a class="app-btn secondary" href="{{ $app('today') }}">Not now</a>
        </div>
        <h2 class="app-sub">Match alerts</h2>
        <p class="app-lead">A notification when a club or sport you follow adds a match. Off until you allow it. Turn it off later in Alerts.</p>
        <div class="app-stack">
            <a class="app-btn" href="{{ $app('alerts') }}">Allow alerts</a>
            <a class="app-btn secondary" href="{{ $app('today') }}">Not now</a>
        </div>
    </div>

@elseif ($screen === 'signin')
    <div class="app-pad app-signin">
        <p class="app-kicker">ShootingSports</p>
        <h1 class="app-hero">Find somewhere to shoot.</h1>
        <p class="app-lead">Matches, clubs and ranges in South Africa. Creating an account is optional. The public register stays open without one.</p>
        <div class="app-stack">
            @if ($ios)
                <a class="app-btn apple" href="{{ $app('you') }}">
                    <svg width="16" height="18" viewBox="0 0 16 18" fill="currentColor" aria-hidden="true"><path d="M13.2 9.5c0-2.2 1.8-3.3 1.9-3.4-1-1.5-2.7-1.7-3.2-1.7-1.4-.1-2.7.8-3.4.8s-1.8-.8-3-.8c-1.5 0-2.9.9-3.7 2.3-1.6 2.7-.4 6.8 1.1 9 .8 1.1 1.7 2.3 2.9 2.2 1.1 0 1.6-.7 3-.7s1.8.7 3 .7 2-.1 2.9-2.2c.7-1 1.2-2 1.5-3.1-3.9-1.5-3.9-5.6 0-6.1ZM10.6 3.2c.6-.8 1-1.8.9-2.9-1 .1-2.1.6-2.8 1.4-.6.7-1.1 1.8-.9 2.8 1.1.1 2.1-.5 2.8-1.3Z"/></svg>
                    Sign in with Apple
                </a>
            @else
                <a class="app-btn google" href="{{ $app('you') }}">
                    <svg width="18" height="18" viewBox="0 0 18 18" aria-hidden="true"><path fill="#4285F4" d="M17.6 9.2c0-.6-.1-1.2-.2-1.8H9v3.4h4.8a4.1 4.1 0 0 1-1.8 2.7v2.2h2.9c1.7-1.6 2.7-3.9 2.7-6.5Z"/><path fill="#34A853" d="M9 18c2.4 0 4.5-.8 6-2.2l-2.9-2.2c-.8.6-1.9.9-3.1.9-2.4 0-4.4-1.6-5.1-3.8H.9v2.3A9 9 0 0 0 9 18Z"/><path fill="#FBBC05" d="M3.9 10.7a5.4 5.4 0 0 1 0-3.4V5H.9a9 9 0 0 0 0 8l3-2.3Z"/><path fill="#EA4335" d="M9 3.6c1.3 0 2.5.5 3.4 1.3l2.6-2.6A9 9 0 0 0 .9 5l3 2.3C4.6 5.2 6.6 3.6 9 3.6Z"/></svg>
                    Continue with Google
                </a>
            @endif
            <a class="app-btn secondary" href="{{ $app('you') }}">Continue with email</a>
        </div>
        <p class="app-fine">By continuing you agree to the <a href="{{ route('terms') }}">Terms of use</a> and acknowledge the <a href="{{ route('privacy') }}">Privacy policy</a>.</p>
        <p class="app-fine">You must be 18 or older. Optional emails stay off until you turn them on. You can delete the account later from You.</p>
    </div>
@endif
