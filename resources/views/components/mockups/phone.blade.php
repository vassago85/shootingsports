@props([
    'platform' => 'ios',
    'screen' => 'login',
])

@php
    $tab = match (true) {
        in_array($screen, ['find', 'clubs', 'club', 'ranges', 'range', 'sports', 'sport', 'industry', 'supplier'], true) => 'find',
        in_array($screen, ['you', 'notifications', 'upgrade', 'log'], true) => 'you',
        in_array($screen, ['matches', 'match', 'calendar', 'map', 'my-calendar'], true) => 'matches',
        $screen === 'home' => 'home',
        default => null,
    };

    $bar = match ($screen) {
        'register' => 'Create account',
        'matches', 'match' => 'Matches',
        'calendar' => 'Calendar',
        'map' => 'Map',
        'my-calendar' => 'My calendar',
        'log' => 'My log',
        'notifications' => 'Notifications',
        'upgrade' => 'Go Pro',
        'you' => 'You',
        'find' => 'Find',
        'clubs', 'club' => 'Clubs',
        'ranges', 'range' => 'Ranges',
        'sports', 'sport' => 'Sports',
        'industry', 'supplier' => 'Industry',
        default => null,
    };

    $href = fn (string $target): string => route('mockups.apps', ['screen' => $target]);

    $back = match ($screen) {
        'register' => 'login',
        'match', 'calendar', 'map', 'my-calendar' => 'matches',
        'log', 'notifications', 'upgrade' => 'you',
        'club' => 'clubs',
        'range' => 'ranges',
        'sport' => 'sports',
        'supplier' => 'industry',
        'clubs', 'ranges', 'sports', 'industry' => 'find',
        default => null,
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
                <span>09:41</span>
                <span class="phone-status-icons" aria-hidden="true">
                    <svg width="16" height="12" viewBox="0 0 16 12"><path d="M1 8h2v3H1zM5 5h2v6H5zM9 3h2v8H9zM13 1h2v10h-2z" fill="currentColor"/></svg>
                    <svg width="15" height="11" viewBox="0 0 15 11"><path d="M7.5 2.2c2 0 3.8.8 5.1 2.1l1-1.1A8.4 8.4 0 0 0 7.5.4 8.4 8.4 0 0 0 1.4 3.2l1 1.1A6.6 6.6 0 0 1 7.5 2.2Zm0 3.2c1.1 0 2.1.4 2.8 1.2l1-1a5.4 5.4 0 0 0-7.6 0l1 1c.7-.8 1.7-1.2 2.8-1.2ZM7.5 8.6a1.2 1.2 0 1 0 0 2.4 1.2 1.2 0 0 0 0-2.4Z" fill="currentColor"/></svg>
                </span>
            </div>

            @if ($bar)
                <div class="phone-bar">
                    @if ($back)
                        <a href="{{ $href($back) }}">{{ $platform === 'ios' ? '‹ Back' : '←' }}</a>
                    @else
                        <span></span>
                    @endif
                    <strong>{{ $bar }}</strong>
                    <span></span>
                </div>
            @endif

            <div class="phone-body">
                {{ $slot }}
            </div>

            @if ($tab)
                <nav class="phone-tabs" aria-label="{{ $platform === 'ios' ? 'iPhone' : 'Android' }}">
                    <a href="{{ $href('home') }}" @class(['on' => $tab === 'home'])>Home</a>
                    <a href="{{ $href('matches') }}" @class(['on' => $tab === 'matches'])>Matches</a>
                    <a href="{{ $href('find') }}" @class(['on' => $tab === 'find'])>Find</a>
                    <a href="{{ $href('you') }}" @class(['on' => $tab === 'you'])>You</a>
                </nav>
                <div class="phone-home" aria-hidden="true"></div>
            @endif
        </div>
    </div>
    <figcaption>{{ $platform === 'ios' ? 'iPhone' : 'Android' }}</figcaption>
</figure>
