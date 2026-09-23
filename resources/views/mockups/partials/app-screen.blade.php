<div class="app-pad">
    @switch($screen)
        @case('login')
            <p class="app-kicker">Sign in</p>
            <h1 class="app-hero">Log in</h1>
            <p class="app-lead">One login for shooters and match directors. We'll take you to the right place.</p>
            <form class="app-form" action="{{ $app('home') }}">
                <label>Email<input type="email" value="paul@example.co.za" readonly></label>
                <label>Password<input type="password" value="password" readonly></label>
                <label class="app-check"><input type="checkbox" checked disabled> Keep me signed in on this device</label>
                <button class="app-btn" type="submit">Log in</button>
            </form>
            <p class="app-fine">New here? <a href="{{ $app('register') }}">Create an account</a>. One login for shooters, match directors, clubs and suppliers.</p>
            @break

        @case('register')
            <p class="app-kicker">Sign up</p>
            <h1 class="app-hero">Create your account</h1>
            <p class="app-lead">One login for shooters, match directors, clubs, series and suppliers. Every account is free.</p>
            <form class="app-form" action="{{ $app('home') }}">
                <label>Your name<input type="text" value="Paul" readonly></label>
                <label>Email<input type="email" value="paul@example.co.za" readonly></label>
                <label>Password<input type="password" value="password" readonly></label>
                <p class="app-kicker">Also register me as</p>
                <label class="app-check"><input type="checkbox" disabled> Match director, club or series admin</label>
                <label class="app-check"><input type="checkbox" disabled> Supplier / industry business</label>
                <button class="app-btn" type="submit">Create account</button>
            </form>
            @break

        @case('home')
            <p class="app-kicker">Signed in</p>
            <h1 class="app-hero">Your shooting</h1>
            @if ($match)
                <p class="app-kicker">Next up</p>
                <a class="app-match" href="{{ $app('match', ['slug' => $match['slug']]) }}">
                    <span class="app-when"><b>{{ $match['day'] }}</b><span>{{ $match['month'] }}</span></span>
                    <span>
                        <strong>{{ $match['title'] }}</strong>
                        <em>{{ collect([$match['discipline'], $match['place']])->filter()->implode(' · ') }}</em>
                    </span>
                </a>
                <a class="app-btn" href="{{ $app('match', ['slug' => $match['slug']]) }}">View match</a>
            @else
                <p class="app-lead">No upcoming matches on the register yet.</p>
            @endif
            <div class="app-links">
                <a href="{{ $app('my-calendar') }}">My calendar</a>
                <a href="{{ $app('log') }}">My log</a>
                <a href="{{ $app('matches') }}">All matches</a>
            </div>
            @break

        @case('matches')
            @forelse ($matches as $row)
                <a class="app-match" href="{{ $app('match', ['slug' => $row['slug']]) }}">
                    <span class="app-when"><small>{{ $row['dow'] }}</small><b>{{ $row['day'] }}</b><span>{{ $row['month'] }}</span></span>
                    <span>
                        <strong>{{ $row['title'] }}</strong>
                        <em>{{ collect([$row['discipline'], $row['place']])->filter()->implode(' · ') }}</em>
                    </span>
                </a>
            @empty
                <p class="app-lead">No upcoming matches on the register yet.</p>
            @endforelse
            @break

        @case('match')
            @if ($match)
                <p class="app-kicker">{{ collect([$match['discipline'], $match['host']])->filter()->implode(' · ') }}</p>
                <h1 class="app-hero">{{ $match['title'] }}</h1>
                <div class="app-date">
                    <b>{{ $match['day'] }}</b>
                    <span>{{ $match['month'] }}</span>
                </div>
                @if ($match['venue'] || $match['place'])
                    <p class="app-lead">{{ collect([$match['venue'], $match['place']])->filter()->implode(' · ') }}</p>
                @endif
                @if ($match['fee'])
                    <p class="app-fine">Entry {{ $match['fee'] }}</p>
                @endif
                <div class="app-stack">
                    <a class="app-btn" href="{{ $app('my-calendar') }}">Add to my calendar</a>
                    <a class="app-btn ghost" href="{{ $app('log') }}">I shot this</a>
                    @if ($match['entry'])
                        <a class="app-btn ghost" href="{{ $app('match', ['slug' => $match['slug']]) }}">Enter match</a>
                    @endif
                </div>
            @else
                <p class="app-lead">No match to show yet.</p>
            @endif
            @break

        @case('calendar')
            <p class="app-lead">Matches by month, the same register as the website calendar.</p>
            @forelse (collect($matches)->groupBy('month') as $month => $rows)
                <h2 class="app-sub">{{ $month }}</h2>
                @foreach ($rows as $row)
                    <a class="app-match" href="{{ $app('match', ['slug' => $row['slug']]) }}">
                        <span class="app-when"><b>{{ $row['day'] }}</b><span>{{ $row['month'] }}</span></span>
                        <span><strong>{{ $row['title'] }}</strong><em>{{ $row['discipline'] }}</em></span>
                    </a>
                @endforeach
            @empty
                <p class="app-lead">No upcoming matches on the register yet.</p>
            @endforelse
            @break

        @case('map')
            <p class="app-lead">Ranges with a published pin. The website map is the full view.</p>
            @forelse ($ranges as $row)
                <a class="app-row" href="{{ $app('range', ['slug' => $row['slug']]) }}">
                    <strong>{{ $row['name'] }}</strong>
                    <em>{{ $row['place'] }}</em>
                </a>
            @empty
                <p class="app-lead">No published ranges yet.</p>
            @endforelse
            @break

        @case('my-calendar')
            <p class="app-kicker">Account</p>
            <h1 class="app-hero">My calendar</h1>
            <p class="app-lead">Matches you saved. Subscribe from the website when you want them in your phone calendar.</p>
            @forelse (array_slice($matches, 0, 4) as $row)
                <a class="app-match" href="{{ $app('match', ['slug' => $row['slug']]) }}">
                    <span class="app-when"><b>{{ $row['day'] }}</b><span>{{ $row['month'] }}</span></span>
                    <span><strong>{{ $row['title'] }}</strong><em>Saved</em></span>
                </a>
            @empty
                <p class="app-lead">Nothing saved yet. Open a match and choose Add to my calendar.</p>
            @endforelse
            @break

        @case('log')
            <p class="app-kicker">My log</p>
            <h1 class="app-hero">Attendance record</h1>
            <p class="app-lead">Every match you have logged. Export the year for a dedicated-status renewal.</p>
            <p class="app-fine">Free tier: 0 slots used. <a href="{{ $app('upgrade') }}">Go Pro for unlimited history.</a></p>
            <a class="app-btn" href="{{ $app('upgrade') }}">Export for renewals</a>
            <p class="app-fine">No matches logged for this year. Use I shot this on a match, or add one that is not on the register.</p>
            @break

        @case('notifications')
            <p class="app-kicker">Account · Notifications</p>
            <h1 class="app-hero">Email preferences</h1>
            <p class="app-lead">Account and operational messages always go out. Everything else is opt-out.</p>
            <label class="app-check"><input type="checkbox" checked disabled> Receive marketing emails</label>
            @foreach ($emailCategories as $category)
                <label class="app-check">
                    <input type="checkbox" @checked($category === \App\Enums\EmailCategory::MatchAlerts) disabled>
                    <span><b>{{ $category->label() }}</b><small>{{ $category->description() }}</small></span>
                </label>
            @endforeach
            <a class="app-btn" href="{{ $app('you') }}">Save preferences</a>
            @break

        @case('upgrade')
            <p class="app-kicker">Pro · Upgrade</p>
            <h1 class="app-hero">Go Pro</h1>
            <p class="app-lead">Unlimited follows, unlimited saved searches, full history, season exports. Cancel any time from your account.</p>
            <a class="app-btn" href="{{ $app('you') }}">Upgrade</a>
            <a class="app-btn ghost" href="{{ $app('you') }}">Cancel subscription</a>
            @break

        @case('you')
            <p class="app-kicker">Account</p>
            <h1 class="app-hero">Paul</h1>
            <div class="app-links">
                <a href="{{ $app('my-calendar') }}">My calendar</a>
                <a href="{{ $app('log') }}">My log</a>
                <a href="{{ $app('notifications') }}">Notifications</a>
                <a href="{{ $app('upgrade') }}">Go Pro</a>
                <a href="{{ $app('login') }}">Sign out</a>
            </div>
            @break

        @case('find')
            <h1 class="app-hero">Find</h1>
            <div class="app-links">
                <a href="{{ $app('clubs') }}"><b>Clubs &amp; series</b><span>Find people shooting your sports.</span></a>
                <a href="{{ $app('ranges') }}"><b>Ranges</b><span>Find somewhere to shoot.</span></a>
                <a href="{{ $app('sports') }}"><b>Sports</b><span>Discover another discipline.</span></a>
                <a href="{{ $app('industry') }}"><b>Industry</b><span>Dealers, gunsmiths, optics, reloading.</span></a>
            </div>
            @break

        @case('clubs')
            @forelse ($clubs as $row)
                <a class="app-row" href="{{ $app('club', ['slug' => $row['slug']]) }}">
                    <strong>{{ $row['name'] }}</strong>
                    <em>{{ collect([$row['type'], $row['place']])->filter()->implode(' · ') }}</em>
                </a>
            @empty
                <p class="app-lead">No published clubs yet.</p>
            @endforelse
            @break

        @case('club')
            @if ($club)
                <p class="app-kicker">{{ $club['type'] }}</p>
                <h1 class="app-hero">{{ $club['name'] }}</h1>
                @if ($club['place'])<p class="app-lead">{{ $club['place'] }}</p>@endif
                <a class="app-btn" href="{{ $app('matches') }}">Upcoming matches</a>
            @else
                <p class="app-lead">No club to show yet.</p>
            @endif
            @break

        @case('ranges')
            @forelse ($ranges as $row)
                <a class="app-row" href="{{ $app('range', ['slug' => $row['slug']]) }}">
                    <strong>{{ $row['name'] }}</strong>
                    <em>{{ collect([$row['place'], $row['distance']])->filter()->implode(' · ') }}</em>
                </a>
            @empty
                <p class="app-lead">No published ranges yet.</p>
            @endforelse
            @break

        @case('range')
            @if ($range)
                <h1 class="app-hero">{{ $range['name'] }}</h1>
                @if ($range['place'])<p class="app-lead">{{ $range['place'] }}</p>@endif
                @if ($range['distance'])<p class="app-fine">{{ $range['distance'] }}</p>@endif
                <a class="app-btn" href="{{ $app('matches') }}">Matches here</a>
            @else
                <p class="app-lead">No range to show yet.</p>
            @endif
            @break

        @case('sports')
            @forelse ($sports as $row)
                <a class="app-row" href="{{ $app('sport', ['slug' => $row['slug']]) }}">
                    <strong>{{ $row['name'] }}</strong>
                    <em>{{ $row['family'] }}</em>
                </a>
            @empty
                <p class="app-lead">No sports listed yet.</p>
            @endforelse
            @break

        @case('sport')
            @if ($sport)
                <p class="app-kicker">{{ $sport['family'] }}</p>
                <h1 class="app-hero">{{ $sport['name'] }}</h1>
                <a class="app-btn" href="{{ $app('matches') }}">Find a match</a>
            @else
                <p class="app-lead">No sport to show yet.</p>
            @endif
            @break

        @case('industry')
            @forelse ($suppliers as $row)
                <a class="app-row" href="{{ $app('supplier', ['slug' => $row['slug']]) }}">
                    <strong>{{ $row['name'] }}</strong>
                    <em>{{ collect([$row['category'], $row['place']])->filter()->implode(' · ') }}</em>
                </a>
            @empty
                <p class="app-lead">No published suppliers yet.</p>
            @endforelse
            @break

        @case('supplier')
            @if ($supplier)
                <p class="app-kicker">{{ $supplier['category'] }}</p>
                <h1 class="app-hero">{{ $supplier['name'] }}</h1>
                @if ($supplier['place'])<p class="app-lead">{{ $supplier['place'] }}</p>@endif
            @else
                <p class="app-lead">No supplier to show yet.</p>
            @endif
            @break
    @endswitch
</div>
