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
    $tabIcon = function (string $name): string {
        $common = 'width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"';

        return match ($name) {
            'home' => '<svg '.$common.'><path d="M4 10.5 12 4l8 6.5V20a1 1 0 0 1-1 1h-5v-6H10v6H5a1 1 0 0 1-1-1z"/></svg>',
            'events' => '<svg '.$common.'><rect x="4" y="5" width="16" height="15" rx="2"/><path d="M8 3v4M16 3v4M4 10h16"/></svg>',
            'find' => '<svg '.$common.'><circle cx="11" cy="11" r="6"/><path d="M20 20l-3.5-3.5"/></svg>',
            'following' => '<svg '.$common.'><path d="m12 4 2.1 4.4 4.9.7-3.5 3.4.8 4.8L12 15.8 7.7 17.3l.8-4.8L5 9.1l4.9-.7z"/></svg>',
            'profile' => '<svg '.$common.'><circle cx="12" cy="8" r="3.2"/><path d="M5.5 19.2a6.5 6.5 0 0 1 13 0"/></svg>',
            default => '',
        };
    };
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
                        <span class="tab-icon">{!! $tabIcon('home') !!}</span>
                        <span>Home</span>
                    </a>
                    <a href="{{ $href('events') }}" @class(['on' => $config['tab'] === 'events'])>
                        <span class="tab-icon">{!! $tabIcon('events') !!}</span>
                        <span>Events</span>
                    </a>
                    <a href="{{ $href('find') }}" @class(['on' => $config['tab'] === 'find'])>
                        <span class="tab-icon">{!! $tabIcon('find') !!}</span>
                        <span>Find</span>
                    </a>
                    <a href="{{ $href('following') }}" @class(['on' => $config['tab'] === 'following'])>
                        <span class="tab-icon">{!! $tabIcon('following') !!}</span>
                        <span>Following</span>
                    </a>
                    <a href="{{ $href('profile') }}" @class(['on' => $config['tab'] === 'profile'])>
                        <span class="tab-icon">{!! $tabIcon('profile') !!}</span>
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
