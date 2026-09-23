@props(['platform' => 'ios', 'screen' => 'home'])

@php
    $ios = $platform === 'ios';

    // Screens that own the whole viewport (no status bar, no tabs, no home bar).
    $fullBleed = in_array($screen, ['splash'], true);
    // Screens in the first-run journey: status bar visible, no tabs, no top bar.
    $onboarding = in_array($screen, ['welcome', 'location', 'sports-choose', 'follow-onboard', 'alerts-onboard'], true);
    // Signed-out state — status bar and content, no tabs.
    $signedOut = $screen === 'signin' || $screen === 'deleted';

    // Which bottom tab is active. Legacy `today` shows Home tab (as per spec).
    $tab = match (true) {
        $screen === 'pack' && request()->filled('match') => 'matches',
        $screen === 'pack' && request()->filled('sport') => 'you',
        $screen === 'pack' => 'you',
        $screen === 'packing' => 'you',
        in_array($screen, ['find', 'clubs', 'club', 'ranges', 'range', 'sports', 'sport', 'suppliers', 'supplier'], true) => 'find',
        in_array($screen, ['you', 'alerts', 'appearance', 'following', 'subscription', 'cancel', 'cancelled', 'plans', 'data', 'delete', 'permissions'], true) => 'you',
        in_array($screen, ['matches', 'today', 'calendar', 'search', 'match'], true) => 'matches',
        default => 'home',
    };

    // Whether a top bar and back arrow should render.
    $rootScreens = ['home', 'matches', 'today', 'find', 'you'];
    $pushed = ! in_array($screen, $rootScreens, true) && ! $fullBleed && ! $onboarding;
    $showTabs = ! $fullBleed && ! $onboarding && ! $signedOut;

    // What the back link should target, plus the bar title.
    $back = match (true) {
        $screen === 'pack' && request()->filled('match') => ['match', 'Match'],
        $screen === 'pack' && request()->filled('sport') => ['packing', 'Packing'],
        $screen === 'pack' => ['you', 'You'],
        $screen === 'packing' => ['you', 'You'],
        $screen === 'search' => ['matches', 'Matches'],
        $screen === 'calendar' => ['matches', 'Matches'],
        $screen === 'suppliers' => ['find', 'Find'],
        $screen === 'match' => ['matches', 'Matches'],
        in_array($screen, ['clubs', 'ranges', 'sports'], true) => ['find', 'Find'],
        $screen === 'club' => ['clubs', 'Clubs'],
        $screen === 'range' => ['ranges', 'Ranges'],
        $screen === 'sport' => ['sports', 'Sports'],
        $screen === 'supplier' => ['suppliers', 'Suppliers'],
        in_array($screen, ['alerts', 'appearance', 'following', 'subscription', 'plans', 'cancel', 'cancelled', 'data', 'permissions'], true) => ['you', 'You'],
        $screen === 'delete' => ['data', 'Your data'],
        default => ['home', 'Home'],
    };

    $bar = match ($screen) {
        'today', 'matches' => 'Matches',
        'calendar' => 'Calendar',
        'search' => 'Search',
        'match' => 'Match',
        'pack' => request()->filled('match') ? 'Pack' : (request()->filled('sport') ? 'Kit' : 'Packing'),
        'packing' => 'Packing',
        'clubs' => 'Clubs',
        'club' => 'Club',
        'ranges' => 'Ranges',
        'range' => 'Range',
        'sports' => 'Sports',
        'sport' => 'Sport',
        'suppliers' => 'Suppliers',
        'supplier' => 'Supplier',
        'alerts' => 'Alerts',
        'appearance' => 'Appearance',
        'following' => 'Following',
        'subscription', 'cancelled' => 'Subscription',
        'cancel' => 'Cancel Pro',
        'plans' => 'Pro',
        'data' => 'Your data',
        'delete' => 'Delete account',
        'permissions' => 'Permissions',
        default => '',
    };

    $closeKm = (int) request()->query('km', 100);
    $carry = array_filter([
        'theme' => request()->query('theme') === 'light' ? 'light' : null,
        'type' => request()->query('type') === 'large' ? 'large' : null,
        'near' => request()->query('near') === '1' && is_numeric(request()->query('lat')) && is_numeric(request()->query('lng')) ? '1' : null,
        'lat' => request()->query('near') === '1' && is_numeric(request()->query('lat')) ? request()->query('lat') : null,
        'lng' => request()->query('near') === '1' && is_numeric(request()->query('lng')) ? request()->query('lng') : null,
        'km' => request()->has('km') && in_array($closeKm, [50, 100, 150], true) ? $closeKm : null,
        'province' => request()->query('province') === 'all'
            ? 'all'
            : \App\Enums\Province::tryFrom(request()->string('province')->toString())?->value,
        'home' => \App\Enums\Province::tryFrom(request()->string('home')->toString())?->value,
    ]);

    $open = function (string $target) use ($mk, $carry): string {
        $params = array_merge(['screen' => $target], $carry);

        if ($target === 'alerts' && request()->query('emails') === 'off') {
            $params['emails'] = 'off';
        }

        if ($target === 'match' && request()->filled('match')) {
            $params['match'] = request()->string('match')->toString();
        }

        if (in_array($target, ['sports', 'sport'], true)) {
            $family = request()->string('family')->toString();

            if (in_array($family, ['rifle', 'handgun', 'shotgun', 'airgun', 'multi'], true)) {
                $params['family'] = $family;
            }

            $typed = trim(request()->string('q')->toString());

            if ($typed !== '') {
                $params['q'] = $typed;
            }
        }

        return $mk('mockups.apps', $params);
    };

    // Match detail action-bar targets.
    $backHref = match (true) {
        $screen === 'pack' && request()->filled('match') => $mk('mockups.apps', array_merge(['screen' => 'match', 'match' => request()->query('match')], $carry)),
        default => null,
    };
@endphp

<figure @class([
    'phone',
    'phone-ios' => $ios,
    'phone-android' => ! $ios,
    'phone-dark' => request()->query('theme') !== 'light',
    'phone-large' => request()->query('type') === 'large',
])>
    <div class="phone-bezel">
        <div @class([
            'phone-glass',
            'phone-splash' => $fullBleed,
            'phone-nochrome' => $fullBleed,
        ])>
            @if ($ios)
                <div class="phone-island" aria-hidden="true"></div>
            @else
                <div class="phone-hole" aria-hidden="true"></div>
            @endif

            @unless ($fullBleed)
                <div class="phone-status" aria-hidden="true">
                    <span>09:41</span>
                    <span class="phone-status-icons">
                        <svg width="15" height="12" viewBox="0 0 15 12" fill="currentColor"><rect x="0" y="8" width="3" height="4" rx=".5"/><rect x="4" y="5" width="3" height="7" rx=".5"/><rect x="8" y="2" width="3" height="10" rx=".5"/><rect x="12" y="0" width="3" height="12" rx=".5"/></svg>
                        <svg width="16" height="12" viewBox="0 0 16 12" fill="currentColor"><path d="M8 3.2c1.8 0 3.4.7 4.6 1.8l1.2-1.3A8.4 8.4 0 0 0 8 .8 8.4 8.4 0 0 0 2.2 3.7l1.2 1.3A6.6 6.6 0 0 1 8 3.2Zm0 3.2c.9 0 1.7.3 2.3.9l1.2-1.3A5 5 0 0 0 8 4.8a5 5 0 0 0-3.5 1.2l1.2 1.3c.6-.6 1.4-.9 2.3-.9ZM8 9.6l2-2.1a2.8 2.8 0 0 0-4 0L8 9.6Z"/></svg>
                        <svg width="24" height="12" viewBox="0 0 24 12"><rect x="0.5" y="0.5" width="20" height="11" rx="2" fill="none" stroke="currentColor"/><rect x="2" y="2" width="14" height="8" rx="1" fill="currentColor"/><rect x="21.5" y="3.5" width="1.5" height="5" rx=".4" fill="currentColor"/></svg>
                    </span>
                </div>
            @endunless

            @if ($pushed)
                <div class="phone-bar">
                    <a class="phone-back" href="{{ $backHref ?? $open($back[0]) }}" @unless ($ios) aria-label="Back" @endunless>
                        @if ($ios)
                            <svg width="10" height="18" viewBox="0 0 10 18" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m8 1-7 8 7 8"/></svg>
                            <span>{{ $back[1] }}</span>
                        @else
                            <x-mockups.icon name="back" />
                        @endif
                    </a>
                    <strong>{{ $bar }}</strong>
                    <span class="phone-bar-actions">
                        @if (in_array($screen, ['matches', 'today'], true))
                            <a href="{{ $open('search') }}" aria-label="Search"><x-mockups.icon name="search" /></a>
                            <a href="{{ $open('calendar') }}" aria-label="Calendar"><x-mockups.icon name="calendar" /></a>
                        @endif
                    </span>
                </div>
            @endif

            <div class="phone-body">
                {{ $slot }}
            </div>

            @if ($showTabs)
                <nav class="phone-tabs" aria-label="{{ $ios ? 'iPhone' : 'Android' }}">
                    <a href="{{ $open('home') }}" @class(['on' => $tab === 'home'])>
                        <x-mockups.icon name="home" :solid="$tab === 'home'" />
                        <span>Home</span>
                    </a>
                    <a href="{{ $open('matches') }}" @class(['on' => $tab === 'matches'])>
                        <x-mockups.icon name="calendar" :solid="$tab === 'matches'" />
                        <span>Matches</span>
                    </a>
                    <a href="{{ $open('find') }}" @class(['on' => $tab === 'find'])>
                        <x-mockups.icon name="compass" :solid="$tab === 'find'" />
                        <span>Find</span>
                    </a>
                    <a href="{{ $open('you') }}" @class(['on' => $tab === 'you'])>
                        <x-mockups.icon name="user" :solid="$tab === 'you'" />
                        <span>You</span>
                    </a>
                </nav>
            @endif

            @unless ($fullBleed)
                <div class="phone-home" aria-hidden="true"></div>
            @endunless
        </div>
    </div>
    <figcaption>{{ $ios ? 'iPhone · App Store' : 'Android · Google Play' }}</figcaption>
</figure>
