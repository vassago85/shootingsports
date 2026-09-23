@if ($screen === 'you')
    <div class="app-pad">
        <div class="app-profile">
            <div class="app-avatar-lg">P</div>
            <div class="app-profile-copy">
                <strong>Paul</strong>
                <em>{{ $close['home_place'] }}</em>
            </div>
        </div>

        <p class="app-sub">Your shooting</p>
        <a class="app-counts" href="{{ $app('following') }}">
            <span><b>3</b><span>sports</span></span>
            <span><b>2</b><span>clubs</span></span>
            <span><b>1</b><span>series</span></span>
        </a>
        <a class="app-see-all" href="{{ $app('following') }}">Manage following <x-mockups.icon name="chevron" /></a>

        <p class="app-sub">Saved</p>
        <div class="app-group">
            <a href="{{ $app('matches') }}"><span class="app-mark"><x-mockups.icon name="bookmark" /></span><span class="app-group-copy"><span>Saved matches</span></span></a>
            <a href="{{ $app('packing') }}"><span class="app-mark"><x-mockups.icon name="bag" /></span><span class="app-group-copy"><span>Packing lists</span></span></a>
            <a href="{{ $app('calendar') }}"><span class="app-mark"><x-mockups.icon name="calendar" /></span><span class="app-group-copy"><span>Calendar</span></span></a>
        </div>

        <p class="app-sub">Preferences</p>
        <div class="app-group">
            <a href="{{ $app('location') }}"><span class="app-mark"><x-mockups.icon name="pin" /></span><span class="app-group-copy"><span>Location</span><small>{{ $close['home_place'] }}</small></span></a>
            <a href="{{ $app('alerts') }}"><span class="app-mark"><x-mockups.icon name="bell" /></span><span class="app-group-copy"><span>Notifications</span><small>{{ $paused ? 'Off' : 'On' }}</small></span></a>
            <a href="{{ $app('appearance') }}"><span class="app-mark"><x-mockups.icon name="sun" /></span><span class="app-group-copy"><span>Appearance</span><small>{{ $appearanceTheme === 'light' ? 'Light' : 'Dark' }}</small></span></a>
        </div>

        <p class="app-sub">Account</p>
        <div class="app-group">
            <a href="{{ $app('subscription') }}"><span class="app-mark"><x-mockups.icon name="card" /></span><span class="app-group-copy"><span>Subscription</span><small>Pro · renews {{ $renews }}</small></span></a>
            <a href="{{ $app('data') }}"><span class="app-mark"><x-mockups.icon name="shield" /></span><span class="app-group-copy"><span>Privacy &amp; data</span></span></a>
            <a href="{{ $app('signin') }}"><span class="app-mark"><x-mockups.icon name="logout" /></span><span class="app-group-copy"><span>Sign out</span></span></a>
        </div>
    </div>

@elseif ($screen === 'following')
    @php
        $followedSports = collect($requestFollowedSports ?? []);
    @endphp
    <div class="app-pad">
        <h1 class="app-hero">Following</h1>
        <p class="app-lead">Matches from these show on your home screen.</p>
        <p class="app-sub">Sports</p>
        <div class="app-group">
            @foreach ($sports->take(8) as $row)
                <a href="{{ $app('sport', ['sport' => $row['slug']]) }}">
                    <span @class(['app-mark', 'tone-'.$row['family']])><x-mockups.icon :name="$familyIcon($row['family'])" /></span>
                    <span class="app-group-copy"><span>{{ $row['name'] }}</span></span>
                </a>
            @endforeach
        </div>
        <a class="app-textlink" href="{{ $app('sports') }}">Browse all sports</a>

        <p class="app-sub">Clubs &amp; series</p>
        <div class="app-group">
            @foreach ($clubs->take(6) as $row)
                <a href="{{ $app('club', ['club' => $row['slug']]) }}">
                    <span class="app-mark"><x-mockups.icon name="users" /></span>
                    <span class="app-group-copy"><span>{{ $row['name'] }}</span><small>{{ $row['place'] }}</small></span>
                </a>
            @endforeach
        </div>

        <p class="app-sub">Province</p>
        <div class="app-chipbar" role="group" aria-label="Home province">
            @foreach (\App\Enums\Province::cases() as $province)
                <a href="{{ $app('following', ['home' => $province->value]) }}" @class(['app-chip', 'on' => $close['home'] === $province->value])>{{ $province->getLabel() }}</a>
            @endforeach
        </div>
    </div>

@elseif ($screen === 'appearance')
    <div class="app-pad">
        <h1 class="app-hero">Appearance</h1>
        <p class="app-sub">Theme</p>
        <div class="app-views" role="group" aria-label="Theme">
            <a href="{{ $app('appearance', ['theme' => 'dark']) }}" @class(['on' => $appearanceTheme !== 'light'])>Dark</a>
            <a href="{{ $app('appearance', ['theme' => 'light']) }}" @class(['on' => $appearanceTheme === 'light'])>Light</a>
        </div>
        <p class="app-sub">Text size</p>
        <div class="app-views" role="group" aria-label="Text size">
            <a href="{{ $app('appearance', ['type' => 'default']) }}" @class(['on' => ! $appearanceLarge])>Default</a>
            <a href="{{ $app('appearance', ['type' => 'large']) }}" @class(['on' => $appearanceLarge])>Larger</a>
        </div>
    </div>

@elseif ($screen === 'alerts')
    <div class="app-pad">
        <h1 class="app-hero">Alerts</h1>
        @if ($paused)
            <p class="app-banner">Optional emails are off. Account emails still arrive.</p>
            <a class="app-btn secondary" href="{{ $app('alerts', ['emails' => 'on']) }}">Turn emails back on</a>
        @else
            <p class="app-lead">One tap stops every optional email.</p>
            <a class="app-btn" href="{{ $app('alerts', ['emails' => 'off']) }}">Turn off optional emails</a>
        @endif
        <ul class="app-list">
            <li><span>New match alerts</span><b>{{ $paused ? 'Off' : 'On' }}</b><small>When a club or sport you follow adds a match.</small></li>
            <li><span>Weekly digest</span><b>Off</b><small>A Monday summary. Off unless you turn it on.</small></li>
            <li><span>Product updates</span><b>{{ $paused ? 'Off' : 'On' }}</b><small>Rare notes about the register.</small></li>
            <li><span>Trial reminders</span><b>{{ $paused ? 'Off' : 'On' }}</b><small>Two emails during a free trial.</small></li>
            <li><span>Account emails</span><b>Always on</b><small>Receipts, password resets, enquiry replies.</small></li>
            <li><span>Push on this phone</span><b>Off</b><small>Stays off until you allow notifications.</small></li>
        </ul>
    </div>
@endif
