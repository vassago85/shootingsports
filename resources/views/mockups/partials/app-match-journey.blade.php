@if ($screen === 'match')
    <div class="app-pad">
        @if ($saved)
            <p class="app-banner">Saved on this phone.</p>
        @endif
        @if ($match)
            @php
                $packCount = count(\App\Support\Mockups\PackingKits::basics($match['discipline_slug'] ?? null, $match['family'] ?? null));
            @endphp
            <span class="app-badge">
                @if ($match['discipline'])<b>{{ $match['discipline'] }}</b>@endif
                @if ($match['level'])<span>· {{ $match['level'] }}</span>@endif
            </span>
            <h1 class="app-hero">{{ $match['title'] }}</h1>

            <div class="app-date-block">
                <div class="app-date-block-num">
                    <b>{{ $match['day'] }}</b>
                    <span>{{ $match['month'] }}</span>
                </div>
                <div class="app-date-block-body">
                    <strong>{{ $match['range'] ?: 'Venue to be confirmed' }}</strong>
                    <em>{{ collect([$match['town'], $match['province']])->filter()->implode(' · ') }}</em>
                </div>
            </div>

            <div class="app-strip">
                @if ($match['time'])<span>{{ $match['time'] }}</span>@endif
                @if ($match['level'])<span>{{ $match['level'] }}</span>@endif
                @if ($kmAway($match))<span>{{ $kmAway($match) }}</span>@endif
            </div>

            <div class="app-stack">
                @if (filled($match['entry_url']))
                    <a class="app-btn" href="{{ $match['entry_url'] }}" target="_blank" rel="noopener">Enter match</a>
                @else
                    <a class="app-btn" href="{{ $app('match', ['match' => $match['slug'], 'saved' => 1]) }}">Save match</a>
                @endif
                @if (filled($match['directions']))
                    <a class="app-btn secondary" href="{{ $match['directions'] }}" target="_blank" rel="noopener">Directions</a>
                @endif
            </div>

            <div class="app-actionrow">
                <a href="{{ $app('match', ['match' => $match['slug'], 'saved' => 1]) }}" @class(['on' => $saved])>
                    <x-mockups.icon name="bookmark" :solid="$saved" />
                    <span>{{ $saved ? 'Saved' : 'Save' }}</span>
                </a>
                <a href="{{ $app('calendar', ['day' => $match['date'] ?? null, 'month' => isset($match['date']) ? substr($match['date'], 0, 7) : null]) }}">
                    <x-mockups.icon name="calendar" />
                    <span>Calendar</span>
                </a>
                <a href="{{ $app('pack', ['match' => $match['slug']]) }}">
                    <x-mockups.icon name="bag" />
                    <span>Pack</span>
                </a>
            </div>

            @php
                $matchSports = $match['discipline_slugs'] ?? [];
                $matchBanners = $bannersFor('matches', $matchSports)->take(1);
                if ($matchBanners->isEmpty()) {
                    $matchBanners = $bannersFor('disciplines', $matchSports)->take(1);
                }
            @endphp
            @include('mockups.partials.app-banners', ['banners' => $matchBanners])

            @if (filled($match['description']))
                <p class="app-sub">About</p>
                <p class="app-lead">{{ \Illuminate\Support\Str::limit($match['description'], 280) }}</p>
            @endif

            <p class="app-sub">Match details</p>
            <dl class="app-facts">
                @if ($match['discipline'])<div><dt>Sport</dt><dd>{{ $match['discipline'] }}</dd></div>@endif
                @if ($match['level'])<div><dt>Level</dt><dd>{{ $match['level'] }}</dd></div>@endif
                @if ($match['organiser'])<div><dt>Organiser</dt><dd>{{ $match['organiser'] }}</dd></div>@endif
                @if ($match['fee'])<div><dt>Entry fee</dt><dd>{{ $match['fee'] }}</dd></div>@endif
                @if ($match['rounds'])<div><dt>Rounds</dt><dd>{{ $match['rounds'] }}</dd></div>@endif
                @if ($match['distance'])<div><dt>Distance</dt><dd>{{ $match['distance'] }}</dd></div>@endif
            </dl>

            @if ($match['range'])
                <p class="app-sub">Venue</p>
                <a class="app-row" href="{{ $match['range_slug'] ? $app('range', ['range' => $match['range_slug']]) : '#' }}">
                    <span class="app-row-main">
                        <span class="app-mark"><x-mockups.icon name="pin" /></span>
                        <span>
                            <strong>{{ $match['range'] }}</strong>
                            <em>{{ collect([$match['town'], $match['province']])->filter()->implode(' · ') }}</em>
                        </span>
                    </span>
                    <x-mockups.icon name="chevron" class="app-chev" />
                </a>
            @endif

            @if ($match['organiser'])
                <p class="app-sub">Organiser</p>
                <a class="app-row" href="{{ $match['organiser_slug'] ? $app('club', ['club' => $match['organiser_slug']]) : '#' }}">
                    <span class="app-row-main">
                        <span class="app-mark"><x-mockups.icon name="users" /></span>
                        <span><strong>{{ $match['organiser'] }}</strong><em>View organiser</em></span>
                    </span>
                    <x-mockups.icon name="chevron" class="app-chev" />
                </a>
            @endif

            @if ($packCount > 0)
                <p class="app-sub">Packing</p>
                <a class="app-row" href="{{ $app('pack', ['match' => $match['slug']]) }}">
                    <span class="app-row-main">
                        <span class="app-mark"><x-mockups.icon name="bag" /></span>
                        <span>
                            <strong>Getting ready?</strong>
                            <em>{{ $packCount }} items on your {{ $match['discipline'] }} checklist</em>
                        </span>
                    </span>
                    <x-mockups.icon name="chevron" class="app-chev" />
                </a>
            @endif
        @else
            <h1 class="app-hero">No match open</h1>
            <p class="app-lead">Nothing upcoming is listed in this preview.</p>
            <a class="app-btn" href="{{ $app('matches') }}">Back to matches</a>
        @endif
    </div>

@elseif ($screen === 'pack' && $packing)
    @php
        $toggle = function (string $key) use ($app, $packing): string {
            $on = in_array($key, $packing['on'], true)
                ? array_values(array_diff($packing['on'], [$key]))
                : array_merge($packing['on'], [$key]);

            return $app('pack', [
                'match' => $packing['match']['slug'] ?? null,
                'on' => $on,
                'added' => $packing['added'],
            ]);
        };
        $bucketOf = function (string $label): string {
            $lower = mb_strtolower($label);
            foreach (['water', 'sunscreen', 'snack', 'lunch', 'hat', 'drink'] as $word) {
                if (str_contains($lower, $word)) {
                    return 'Personal';
                }
            }
            foreach (['vest', 'choke', 'bipod', 'bag', 'rest', 'dope', 'mat', 'glove'] as $word) {
                if (str_contains($lower, $word)) {
                    return 'Match';
                }
            }

            return 'Essential';
        };
        $basics = array_values(array_filter($packing['rows'], fn (array $row): bool => ! $row['custom']));
        $extras = array_values(array_filter($packing['rows'], fn (array $row): bool => $row['custom']));
        $grouped = ['Essential' => [], 'Match' => [], 'Personal' => []];
        foreach ($basics as $row) {
            $grouped[$bucketOf($row['label'])][] = $row;
        }
        $total = count($packing['rows']);
        $done = count(array_filter($packing['rows'], fn (array $row): bool => $row['on']));
        $ready = $total > 0 && $done === $total;
    @endphp
    <div class="app-pad">
        @if ($packing['match'])
            <h1 class="app-hero">Pack for {{ $packing['match']['title'] }}</h1>
            <p class="app-lead">{{ collect([$packing['sport'], $packing['match']['date_label'] ?? null])->filter()->implode(' · ') }}</p>

            @if ($ready)
                <p class="app-banner">Ready to shoot. Everything is packed.</p>
            @elseif ($total > 0)
                <div class="app-progress">
                    <div class="app-progress-head">
                        <strong>{{ $done }} / {{ $total }} packed</strong>
                        <span>{{ (int) round($done / $total * 100) }}%</span>
                    </div>
                    <div class="app-progress-bar"><i style="width: {{ (int) round($done / $total * 100) }}%"></i></div>
                </div>
            @endif

            @foreach ($grouped as $heading => $rows)
                @if ($rows !== [])
                    <p class="app-sub">{{ $heading }}</p>
                    <div class="app-checks">
                        @foreach ($rows as $row)
                            <a class="app-check {{ $row['on'] ? 'on' : '' }}" href="{{ $toggle($row['key']) }}">
                                <span class="app-box" aria-hidden="true"></span>
                                <span>{{ $row['label'] }}</span>
                            </a>
                        @endforeach
                    </div>
                @endif
            @endforeach

            @if ($extras !== [])
                <p class="app-sub">Added</p>
                <div class="app-checks">
                    @foreach ($extras as $row)
                        <a class="app-check {{ $row['on'] ? 'on' : '' }}" href="{{ $toggle($row['key']) }}">
                            <span class="app-box" aria-hidden="true"></span>
                            <span>{{ $row['label'] }}</span>
                        </a>
                    @endforeach
                </div>
            @endif

            <form class="app-add" method="get" action="{{ $mk('mockups.apps') }}">
                <input type="hidden" name="screen" value="pack">
                <input type="hidden" name="match" value="{{ $packing['match']['slug'] }}">
                @foreach ($packing['on'] as $key)
                    <input type="hidden" name="on[]" value="{{ $key }}">
                @endforeach
                @foreach ($packing['added'] as $label)
                    <input type="hidden" name="added[]" value="{{ $label }}">
                @endforeach
                <label><input name="item" maxlength="80" placeholder="Add an item" aria-label="Add an item"></label>
                <button type="submit">Add</button>
            </form>
        @else
            <h1 class="app-hero">That match isn't listed</h1>
            <a class="app-btn" href="{{ $app('matches') }}">Upcoming matches</a>
        @endif
    </div>

@elseif ($screen === 'pack' && $kit)
    <div class="app-pad">
        <p class="app-kicker">{{ $kit['name'] }}</p>
        <h1 class="app-hero">Default checklist</h1>
        <p class="app-lead">Your starting list for every {{ $kit['name'] }} match. Tick items off when you pack for a specific match.</p>
        @if ($kit['items'] !== [])
            <div class="app-checks">
                @foreach ($kit['items'] as $item)
                    <div class="app-check"><span class="app-box" aria-hidden="true"></span><span>{{ $item['label'] }}</span></div>
                @endforeach
            </div>
        @else
            <p class="app-empty">No starter list for this sport yet.</p>
        @endif
        @if ($kit['matches']->isNotEmpty())
            <p class="app-sub">Pack for a match</p>
            @foreach ($kit['matches'] as $row)
                <a class="app-row" href="{{ $app('pack', ['match' => $row['slug']]) }}">
                    <span class="app-row-main">
                        <span class="app-when" style="min-height: auto; padding: 4px 8px;"><b style="font-size: 16px;">{{ $row['day'] }}</b><span>{{ $row['month'] }}</span></span>
                        <span><strong>{{ $row['title'] }}</strong></span>
                    </span>
                    <x-mockups.icon name="chevron" class="app-chev" />
                </a>
            @endforeach
        @endif
    </div>

@else
    @php
        $mine = $kits->take(3);
        $rest = $kits->slice(3)->values();
        $showAll = request()->query('all') === '1';
        $kitQuery = trim(request()->string('q')->toString());
        if ($kitQuery !== '') {
            $rest = $kits->filter(fn (array $row): bool => str_contains(mb_strtolower($row['name']), mb_strtolower($kitQuery)))->values();
            $showAll = true;
        }
    @endphp
    <div class="app-pad">
        <h1 class="app-hero">Packing</h1>
        <p class="app-lead">Reusable checklists for the sports you shoot.</p>

        <p class="app-sub">My sports</p>
        <div class="app-group">
            @foreach ($mine as $row)
                <a href="{{ $app('pack', ['sport' => $row['slug']]) }}">
                    <span class="app-mark"><x-mockups.icon name="bag" /></span>
                    <span class="app-group-copy"><span>{{ $row['name'] }}</span><small>{{ $row['count'] }} items</small></span>
                </a>
            @endforeach
        </div>

        <p class="app-sub">Other sports</p>
        <form class="app-search-form" method="get" action="{{ $mk('mockups.apps') }}">
            <input type="hidden" name="screen" value="packing">
            <label class="app-search-field">
                <x-mockups.icon name="search" />
                <input type="search" name="q" value="{{ $kitQuery }}" placeholder="Search sports" aria-label="Search sports">
            </label>
        </form>
        @if ($showAll)
            <div class="app-group">
                @forelse ($rest as $row)
                    <a href="{{ $app('pack', ['sport' => $row['slug']]) }}">
                        <span class="app-mark"><x-mockups.icon name="bag" /></span>
                        <span class="app-group-copy"><span>{{ $row['name'] }}</span><small>{{ $row['count'] }} items</small></span>
                    </a>
                @empty
                    <p class="app-empty">No sports match that.</p>
                @endforelse
            </div>
        @else
            <a class="app-see-all" href="{{ $app('packing', ['all' => 1]) }}">Browse all sports <x-mockups.icon name="chevron" /></a>
        @endif
    </div>
@endif
