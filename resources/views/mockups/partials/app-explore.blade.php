@if ($screen === 'find')
    <div class="app-pad">
        <h1 class="app-hero">Find</h1>
        <form class="app-search-form" method="get" action="{{ $mk('mockups.apps') }}">
            <input type="hidden" name="screen" value="search">
            <label class="app-search-field">
                <x-mockups.icon name="search" />
                <input type="search" name="q" placeholder="Search clubs, ranges and sports" aria-label="Search">
            </label>
        </form>

        @if ($ranges->isNotEmpty())
            <p class="app-sub">Near you</p>
            @foreach ($ranges->take(3) as $row)
                <a class="app-row" href="{{ $app('range', ['range' => $row['slug']]) }}">
                    <span class="app-row-main">
                        <span class="app-mark"><x-mockups.icon name="range" /></span>
                        <span>
                            <strong>{{ $row['name'] }}</strong>
                            <em>{{ collect([$row['place'], $kmAway($row)])->filter()->implode(' · ') }}</em>
                        </span>
                    </span>
                    <x-mockups.icon name="chevron" class="app-chev" />
                </a>
            @endforeach
        @endif

        <div class="app-group" style="margin-top: 20px;">
            <a href="{{ $app('clubs') }}">
                <span class="app-mark"><x-mockups.icon name="users" /></span>
                <span class="app-group-copy"><span>Clubs &amp; series</span><small>Find people shooting your sports.</small></span>
            </a>
            <a href="{{ $app('ranges') }}">
                <span class="app-mark"><x-mockups.icon name="range" /></span>
                <span class="app-group-copy"><span>Ranges</span><small>Find somewhere to shoot.</small></span>
            </a>
            <a href="{{ $app('sports') }}">
                <span class="app-mark"><x-mockups.icon name="target" /></span>
                <span class="app-group-copy"><span>Sports</span><small>Discover another discipline.</small></span>
            </a>
        </div>

        <p class="app-sub">Suppliers</p>
        <a class="app-row" href="{{ $app('suppliers') }}">
            <span class="app-row-main">
                <span class="app-mark"><x-mockups.icon name="store" /></span>
                <span>
                    <strong>Dealers and gunsmiths</strong>
                    <em>Optics, reloading and other shooting businesses.</em>
                </span>
            </span>
            <x-mockups.icon name="chevron" class="app-chev" />
        </a>
    </div>

@elseif ($screen === 'clubs')
    <div class="app-pad">
        <div class="app-chipbar" role="group" aria-label="Filters">
            <a class="app-chip" href="{{ $app('following') }}">Following</a>
            <a @class(['app-chip', 'on' => ($close['gps'] ?? false) === true]) href="{{ $app('clubs', ['near' => 'denied']) }}" data-app-near="{{ $app('clubs', ['near' => '1', 'km' => 100]) }}">Near me</a>
            <a class="app-chip" href="{{ $app('sports') }}">Sport</a>
        </div>
        @forelse ($clubs->take(20) as $row)
            <a class="app-row" href="{{ $app('club', ['club' => $row['slug']]) }}">
                <span class="app-row-main">
                    <span class="app-mark"><x-mockups.icon :name="($row['type'] ?? '') === 'Series' ? 'flag' : 'users'" /></span>
                    <span>
                        <strong>{{ $row['name'] }}</strong>
                        <em>{{ collect([implode(' · ', array_slice($row['disciplines'], 0, 2)), $row['place'], $row['upcoming_count'].' upcoming'])->filter()->implode(' · ') }}</em>
                    </span>
                </span>
                <x-mockups.icon name="chevron" class="app-chev" />
            </a>
        @empty
            <p class="app-empty"><strong>No clubs here</strong>None are listed for this area yet.</p>
        @endforelse
    </div>

@elseif ($screen === 'club')
    <div class="app-pad">
        @if ($club)
            @php $clubMatches = $matches->filter(fn (array $row): bool => ($row['organiser_slug'] ?? null) === $club['slug'])->values(); @endphp
            <p class="app-kicker">{{ collect([$club['type'], implode(' · ', array_slice($club['disciplines'], 0, 3))])->filter()->implode(' · ') }}</p>
            <h1 class="app-hero">{{ $club['name'] }}</h1>
            @if (filled($club['place']))<p class="app-lead">{{ $club['place'] }}</p>@endif
            <div class="app-inline">
                <a class="app-btn tonal" href="{{ $app('following') }}">Following</a>
                @if ($club['website'])<a class="app-btn secondary" href="{{ $club['website'] }}" target="_blank" rel="noopener">Website</a>@endif
                @if ($club['phone'])<a class="app-btn secondary" href="tel:{{ $club['phone'] }}">Contact</a>@endif
            </div>

            @if ($clubMatches->isNotEmpty())
                <p class="app-sub">Next match</p>
                @php $next = $clubMatches->first(); @endphp
                <a class="app-featured" href="{{ $app('match', ['match' => $next['slug']]) }}">
                    <span class="app-eyebrow">{{ $next['dow'] }} {{ $next['day'] }} {{ $next['month'] }}</span>
                    <h2 class="app-featured-title">{{ $next['title'] }}</h2>
                    <p class="app-featured-meta">{{ collect([$next['discipline'], $next['town']])->filter()->implode(' · ') }}</p>
                </a>
                @if ($clubMatches->count() > 1)
                    <p class="app-sub">Upcoming</p>
                    @foreach ($clubMatches->slice(1)->take(4) as $row)
                        <a class="app-match" href="{{ $app('match', ['match' => $row['slug']]) }}">
                            <span class="app-when"><b>{{ $row['day'] }}</b><span>{{ $row['month'] }}</span></span>
                            <span><strong>{{ $row['title'] }}</strong><em>{{ $row['discipline'] }}</em></span>
                        </a>
                    @endforeach
                @endif
            @endif

            @if ($club['range'])
                <p class="app-sub">Where they shoot</p>
                <a class="app-row" href="{{ $club['range_slug'] ? $app('range', ['range' => $club['range_slug']]) : '#' }}">
                    <span class="app-row-main">
                        <span class="app-mark"><x-mockups.icon name="range" /></span>
                        <span><strong>{{ $club['range'] }}</strong></span>
                    </span>
                    <x-mockups.icon name="chevron" class="app-chev" />
                </a>
            @endif

            @if (filled($club['description']))
                <p class="app-sub">About</p>
                <p class="app-lead">{{ \Illuminate\Support\Str::limit($club['description'], 320) }}</p>
            @endif
        @else
            <h1 class="app-hero">No club open</h1>
            <a class="app-btn" href="{{ $app('clubs') }}">All clubs</a>
        @endif
    </div>

@elseif ($screen === 'ranges')
    @php $rangeView = request()->query('view') === 'map' ? 'map' : 'list'; @endphp
    <div class="app-pad">
        <div class="app-views" role="group" aria-label="Range view">
            <a href="{{ $app('ranges', ['view' => null]) }}" @class(['on' => $rangeView === 'list'])>List</a>
            <a href="{{ $app('ranges', ['view' => 'map']) }}" @class(['on' => $rangeView === 'map'])>Map</a>
        </div>
        <div class="app-chipbar" role="group" aria-label="Filters">
            <a @class(['app-chip', 'on' => ($close['gps'] ?? false) === true]) href="{{ $app('ranges', ['near' => 'denied']) }}" data-app-near="{{ $app('ranges', ['near' => '1', 'km' => 100]) }}">Near me</a>
            <a class="app-chip" href="{{ $app('ranges', ['km' => 100]) }}">Distance</a>
            <a class="app-chip" href="{{ $app('sports') }}">Sport</a>
        </div>
        @if ($rangeView === 'map')
            <p class="app-empty"><strong>Map view</strong>Ranges are plotted from their coordinates. List view shows the detail.</p>
        @endif
        @php $rangeBannerShown = false; @endphp
        @forelse ($ranges->take(20) as $row)
            <a class="app-row" href="{{ $app('range', ['range' => $row['slug']]) }}">
                <span class="app-row-main">
                    <span class="app-mark"><x-mockups.icon name="range" /></span>
                    <span>
                        <strong>{{ $row['name'] }}</strong>
                        <em>{{ collect([$row['place'], $kmAway($row), $row['max_distance'], $row['upcoming_count'].' upcoming'])->filter()->implode(' · ') }}</em>
                    </span>
                </span>
                <x-mockups.icon name="chevron" class="app-chev" />
            </a>
            @if ($loop->iteration === 3)
                @include('mockups.partials.app-banners', ['banners' => $bannersFor('ranges')->take(1)])
                @php $rangeBannerShown = true; @endphp
            @endif
        @empty
            <p class="app-empty"><strong>No ranges here</strong>None are listed for this area yet.</p>
        @endforelse
        @if (! $rangeBannerShown)
            @include('mockups.partials.app-banners', ['banners' => $bannersFor('ranges')->take(1)])
        @endif
    </div>

@elseif ($screen === 'range')
    <div class="app-pad">
        @if ($range)
            @php $rangeMatches = $matches->filter(fn (array $row): bool => ($row['range_slug'] ?? null) === $range['slug'])->values(); @endphp
            <h1 class="app-hero">{{ $range['name'] }}</h1>
            <p class="app-lead">{{ collect([$range['place'], $kmAway($range)])->filter()->implode(' · ') }}</p>
            @if (filled($range['directions']))
                <a class="app-btn" href="{{ $range['directions'] }}" target="_blank" rel="noopener">Directions</a>
            @endif

            @if ($rangeMatches->isNotEmpty())
                <p class="app-sub">Upcoming matches</p>
                @foreach ($rangeMatches->take(4) as $row)
                    <a class="app-match" href="{{ $app('match', ['match' => $row['slug']]) }}">
                        <span class="app-when"><b>{{ $row['day'] }}</b><span>{{ $row['month'] }}</span></span>
                        <span><strong>{{ $row['title'] }}</strong><em>{{ $row['discipline'] }}</em></span>
                    </a>
                @endforeach
            @endif

            @if ($range['disciplines'] !== [])
                <p class="app-sub">Sports</p>
                <div class="app-follows">
                    @foreach ($range['disciplines'] as $name)
                        <span class="app-follow">{{ $name }}</span>
                    @endforeach
                </div>
            @endif

            @if ($range['facilities'] !== [])
                <p class="app-sub">Facilities</p>
                <div class="app-follows">
                    @foreach ($range['facilities'] as $facility)
                        <span class="app-follow off">{{ $facility }}</span>
                    @endforeach
                </div>
            @endif

            @if ($range['clubs'] !== [])
                <p class="app-sub">Clubs using this range</p>
                <div class="app-group">
                    @foreach (array_slice($range['clubs'], 0, 6) as $name)
                        <a href="{{ $app('clubs') }}"><span class="app-mark"><x-mockups.icon name="users" /></span><span class="app-group-copy"><span>{{ $name }}</span></span></a>
                    @endforeach
                </div>
            @endif

            @if (filled($range['notes']))
                <p class="app-sub">About</p>
                <p class="app-lead">{{ \Illuminate\Support\Str::limit($range['notes'], 320) }}</p>
            @endif
        @else
            <h1 class="app-hero">No range open</h1>
            <a class="app-btn" href="{{ $app('ranges') }}">All ranges</a>
        @endif
    </div>

@elseif ($screen === 'sports')
    @php
        $families = ['rifle' => 'Rifle', 'handgun' => 'Handgun', 'shotgun' => 'Shotgun', 'airgun' => 'Airgun', 'multi' => 'Multi-gun'];
        $sportQuery = trim(request()->string('q')->toString());
        $openFamily = request()->string('family')->toString();
        $openFamily = array_key_exists($openFamily, $families) ? $openFamily : '';
        $yours = $sports->take(3);
    @endphp
    <div class="app-pad">
        <h1 class="app-hero">Sports</h1>
        <form class="app-search-form" method="get" action="{{ $mk('mockups.apps') }}">
            <input type="hidden" name="screen" value="sports">
            <label class="app-search-field">
                <x-mockups.icon name="search" />
                <input type="search" name="q" value="{{ $sportQuery }}" placeholder="Search sports" aria-label="Search sports">
            </label>
        </form>

        @if ($sportQuery === '' && $openFamily === '')
            <p class="app-sub">Your sports</p>
            <div class="app-group">
                @foreach ($yours as $row)
                    <a href="{{ $app('sport', ['sport' => $row['slug']]) }}">
                        <span @class(['app-mark', 'tone-'.$row['family']])><x-mockups.icon :name="$familyIcon($row['family'])" /></span>
                        <span class="app-group-copy"><span>{{ $row['name'] }}</span><small>{{ $row['upcoming_count'] }} upcoming</small></span>
                    </a>
                @endforeach
            </div>
            @foreach ($families as $value => $label)
                @php $group = $sports->filter(fn (array $sport): bool => $sport['family'] === $value); @endphp
                @if ($group->isNotEmpty())
                    <a class="app-row" href="{{ $app('sports', ['family' => $value]) }}">
                        <span class="app-row-main">
                            <span @class(['app-mark', 'tone-'.$value])><x-mockups.icon :name="$familyIcon($value)" /></span>
                            <span><strong>{{ $label }}</strong><em>{{ $group->count() }} sports</em></span>
                        </span>
                        <x-mockups.icon name="chevron" class="app-chev" />
                    </a>
                @endif
            @endforeach
        @else
            @php
                $rows = $sports->filter(function (array $sport) use ($openFamily, $sportQuery): bool {
                    if ($openFamily !== '' && $sport['family'] !== $openFamily) {
                        return false;
                    }

                    return $sportQuery === '' || str_contains(mb_strtolower($sport['name']), mb_strtolower($sportQuery));
                });
            @endphp
            @if ($openFamily !== '')
                <p class="app-sub">{{ $families[$openFamily] }}</p>
            @endif
            <div class="app-group">
                @forelse ($rows as $row)
                    <a href="{{ $app('sport', ['sport' => $row['slug'], 'family' => $openFamily !== '' ? $openFamily : null]) }}">
                        <span @class(['app-mark', 'tone-'.$row['family']])><x-mockups.icon :name="$familyIcon($row['family'])" /></span>
                        <span class="app-group-copy"><span>{{ $row['name'] }}</span><small>{{ $row['upcoming_count'] }} upcoming</small></span>
                    </a>
                @empty
                    <p class="app-empty">No sports match that.</p>
                @endforelse
            </div>
            <a class="app-textlink" href="{{ $app('sports', ['family' => null, 'q' => null]) }}">All sports</a>
        @endif
    </div>

@elseif ($screen === 'sport')
    <div class="app-pad">
        @if ($sport)
            <p class="app-kicker">{{ $sport['family_label'] }}</p>
            <h1 class="app-hero">{{ $sport['name'] }}</h1>
            @if ($sport['blurb'])<p class="app-lead">{{ $sport['blurb'] }}</p>@endif
            <a class="app-btn tonal" href="{{ $app('following') }}" style="margin-bottom: 16px;">Following</a>

            <div class="app-counts">
                <span><b>{{ $sportMatches->count() }}</b><span>upcoming</span></span>
                <span><b>{{ $sport['clubs_count'] }}</b><span>clubs</span></span>
                <span><b>{{ $sport['ranges_count'] }}</b><span>ranges</span></span>
            </div>

            <a class="app-btn" href="{{ $app('matches') }}">Find a match</a>

            @php
                $sportBanners = $bannersFor('disciplines', array_values(array_filter([
                    $sport['slug'],
                    ...array_column($sport['children'] ?? [], 'slug'),
                ])))->take(1);
            @endphp
            @include('mockups.partials.app-banners', ['banners' => $sportBanners])

            @if ($sportMatches->isNotEmpty())
                <p class="app-sub">Upcoming</p>
                @foreach ($sportMatches->take(4) as $row)
                    <a class="app-match" href="{{ $app('match', ['match' => $row['slug']]) }}">
                        <span class="app-when"><b>{{ $row['day'] }}</b><span>{{ $row['month'] }}</span></span>
                        <span><strong>{{ $row['title'] }}</strong><em>{{ collect([$row['town'] ?: $row['province'], $kmAway($row)])->filter()->implode(' · ') }}</em></span>
                    </a>
                @endforeach
            @endif

            @php $sportClubs = $clubs->filter(fn (array $row): bool => in_array($sport['slug'], $row['discipline_slugs'] ?? [], true))->take(4); @endphp
            @if ($sportClubs->isNotEmpty())
                <p class="app-sub">Nearby clubs</p>
                @foreach ($sportClubs as $row)
                    <a class="app-row" href="{{ $app('club', ['club' => $row['slug']]) }}">
                        <span class="app-row-main"><span class="app-mark"><x-mockups.icon name="users" /></span><span><strong>{{ $row['name'] }}</strong><em>{{ $row['place'] }}</em></span></span>
                        <x-mockups.icon name="chevron" class="app-chev" />
                    </a>
                @endforeach
            @endif

            @php $sportRanges = $ranges->filter(fn (array $row): bool => in_array($sport['slug'], $row['discipline_slugs'] ?? [], true))->take(4); @endphp
            @if ($sportRanges->isNotEmpty())
                <p class="app-sub">Where to shoot</p>
                @foreach ($sportRanges as $row)
                    <a class="app-row" href="{{ $app('range', ['range' => $row['slug']]) }}">
                        <span class="app-row-main"><span class="app-mark"><x-mockups.icon name="range" /></span><span><strong>{{ $row['name'] }}</strong><em>{{ $row['place'] }}</em></span></span>
                        <x-mockups.icon name="chevron" class="app-chev" />
                    </a>
                @endforeach
            @endif

            <p class="app-sub">Learn</p>
            <a class="app-row" href="{{ $app('pack', ['sport' => $sport['slug']]) }}">
                <span class="app-row-main"><span class="app-mark"><x-mockups.icon name="bag" /></span><span><strong>What do I need?</strong><em>The {{ $sport['name'] }} packing list</em></span></span>
                <x-mockups.icon name="chevron" class="app-chev" />
            </a>
        @else
            <h1 class="app-hero">No sport open</h1>
            <a class="app-btn" href="{{ $app('sports') }}">All sports</a>
        @endif
    </div>
@endif
