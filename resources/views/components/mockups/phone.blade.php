@props([
    'platform' => 'ios',
    'screen' => 'home',
])

@php
    $config = match ($screen) {
        'onboarding' => ['title' => 'Welcome', 'kicker' => 'Set up ShootingSports', 'tab' => null],
        'home' => ['title' => 'Discover', 'kicker' => 'This weekend', 'tab' => 'home'],
        'events' => ['title' => 'Events', 'kicker' => 'Calendar and filters', 'tab' => 'events'],
        'detail' => ['title' => 'Event detail', 'kicker' => 'Match', 'tab' => 'events'],
        'find' => ['title' => 'Find', 'kicker' => 'Map and list', 'tab' => 'find'],
        'sports' => ['title' => 'Sports', 'kicker' => 'Disciplines', 'tab' => 'home'],
        'following' => ['title' => 'Following', 'kicker' => 'Saved and followed', 'tab' => 'following'],
        'activity' => ['title' => 'Activity', 'kicker' => 'Notifications', 'tab' => 'following'],
        'profile' => ['title' => 'Profile', 'kicker' => 'Preferences', 'tab' => 'profile'],
        'organiser' => ['title' => 'Organiser', 'kicker' => 'Submit and manage', 'tab' => 'profile'],
        default => ['title' => 'ShootingSports', 'kicker' => 'Companion app', 'tab' => null],
    };

    $href = fn (string $target): string => route('mockups.apps', ['screen' => $target]);
@endphp

<figure class="phone phone-{{ $platform }}">
    <div class="phone-bezel">
        <div class="phone-glass">
            @if ($platform === 'ios')
                <div class="phone-island" aria-hidden="true"></div>
            @else
                <div class="phone-hole" aria-hidden="true"></div>
            @endif

            <div class="phone-status">
                <span>{{ $platform === 'ios' ? '9:41' : '09:41' }}</span>
                <span class="icons" aria-hidden="true">
                    <span class="dot"></span>
                    <span class="dot"></span>
                    <span class="dot"></span>
                    <span>78%</span>
                </span>
            </div>

            <div class="phone-appbar">
                @if ($platform === 'ios')
                    <div class="brandmark" aria-hidden="true">SS</div>
                @else
                    <button class="icon-btn" type="button" aria-label="Menu">☰</button>
                @endif
                <div class="title-block">
                    <div class="kicker">{{ $config['kicker'] }}</div>
                    <div class="app-title">{{ $config['title'] }}</div>
                </div>
                <button class="icon-btn" type="button" aria-label="Search">⌕</button>
                @if ($platform === 'ios')
                    <button class="icon-btn" type="button" aria-label="Notifications">◦</button>
                @endif
            </div>

            <div @class(['phone-body', 'no-tabs' => ! $config['tab']])>
                {{ $slot }}
            </div>

            @if ($config['tab'])
                <nav class="phone-tabs" aria-label="{{ $platform === 'ios' ? 'iPhone tabs' : 'Android tabs' }}">
                    <a href="{{ $href('home') }}" @class(['on' => $config['tab'] === 'home'])>
                        <span class="tab-icon" aria-hidden="true">⌂</span>
                        <span>Home</span>
                    </a>
                    <a href="{{ $href('events') }}" @class(['on' => $config['tab'] === 'events'])>
                        <span class="tab-icon" aria-hidden="true">□</span>
                        <span>Events</span>
                    </a>
                    <a href="{{ $href('find') }}" @class(['on' => $config['tab'] === 'find'])>
                        <span class="tab-icon" aria-hidden="true">⌖</span>
                        <span>Find</span>
                    </a>
                    <a href="{{ $href('following') }}" @class(['on' => $config['tab'] === 'following'])>
                        <span class="tab-icon" aria-hidden="true">★</span>
                        <span>Following</span>
                    </a>
                    <a href="{{ $href('profile') }}" @class(['on' => $config['tab'] === 'profile'])>
                        <span class="tab-icon" aria-hidden="true">◌</span>
                        <span>Profile</span>
                    </a>
                </nav>
            @endif
        </div>
    </div>
    <figcaption>
        {{ $platform === 'ios' ? 'iPhone' : 'Android' }}
        <small>{{ $platform === 'ios' ? 'Native tab bar, large titles' : 'Material app bar, filter chips' }}</small>
    </figcaption>
</figure>
