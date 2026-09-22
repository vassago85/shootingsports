@props(['platform' => 'ios', 'screen' => 'today'])

@php
    $ios = $platform === 'ios';
    $roots = ['today', 'find', 'suppliers', 'you'];
    $pushed = ! in_array($screen, $roots, true);
    $showTabs = ! in_array($screen, ['signin', 'deleted'], true);
    $packingMatch = $screen === 'pack' && request()->filled('match');
    $tab = match (true) {
        $screen === 'pack' && request()->filled('match') => 'today',
        $screen === 'pack' && request()->filled('sport') => 'find',
        $screen === 'pack' => 'you',
        in_array($screen, ['find', 'clubs', 'club', 'ranges', 'range', 'sports', 'sport'], true) => 'find',
        in_array($screen, ['suppliers', 'supplier'], true) => 'suppliers',
        in_array($screen, ['you', 'alerts', 'subscription', 'cancel', 'cancelled', 'plans', 'data', 'delete'], true) => 'you',
        $screen === 'calendar' => 'today',
        default => 'today',
    };
    $back = match (true) {
        $packingMatch => ['match', 'Match'],
        $screen === 'search' => ['today', 'Matches'],
        $screen === 'pack' && request()->filled('sport') => ['pack', 'Pack'],
        $screen === 'pack' => ['you', 'You'],
        $screen === 'calendar' => ['today', 'Matches'],
        in_array($screen, ['match', 'permissions'], true) => ['today', 'Matches'],
        in_array($screen, ['clubs', 'ranges', 'sports'], true) => ['find', 'Find'],
        $screen === 'club' => ['clubs', 'Clubs & series'],
        $screen === 'range' => ['ranges', 'Ranges'],
        $screen === 'sport' => ['sports', 'Sports'],
        $screen === 'supplier' => ['suppliers', 'Suppliers'],
        in_array($screen, ['alerts', 'subscription', 'plans', 'cancelled', 'data'], true) => ['you', 'You'],
        $screen === 'cancel' => ['you', 'You'],
        $screen === 'delete' => ['data', 'Your data'],
        $screen === 'signin' => ['today', 'Matches'],
        $screen === 'deleted' => ['signin', 'Sign in'],
        default => ['today', 'Matches'],
    };
    $bar = match (true) {
        $screen === 'pack' && request()->filled('match') => 'Pack',
        $screen === 'pack' && request()->filled('sport') => 'Kit',
        $screen === 'pack' => 'Packing',
        default => match ($screen) {
            'calendar' => 'Calendar',
            'match' => 'Match',
            'search' => 'Search',
            'permissions' => 'Near me',
            'clubs' => 'Clubs & series',
            'club' => 'Club',
            'ranges' => 'Ranges',
            'range' => 'Range',
            'sports' => 'Sports',
            'sport' => 'Sport',
            'supplier' => 'Supplier',
            'alerts' => 'Alerts',
            'subscription', 'cancelled' => 'Subscription',
            'cancel' => 'Cancel',
            'plans' => 'ShootingSports Pro',
            'data' => 'Your data',
            'delete' => 'Delete account',
            'signin' => 'Sign in',
            'deleted' => 'Account',
            default => '',
        },
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
    $backHref = $packingMatch
        ? $mk('mockups.apps', array_merge(['screen' => 'match', 'match' => request()->query('match')], $carry))
        : null;
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
@endphp

<figure @class(['phone', 'phone-ios' => $ios, 'phone-android' => ! $ios, 'phone-dark' => request()->query('theme') !== 'light', 'phone-large' => request()->query('type') === 'large'])>
    <div class="phone-bezel">
        <div class="phone-glass">
            @if ($ios)
                <div class="phone-island" aria-hidden="true"></div>
            @else
                <div class="phone-hole" aria-hidden="true"></div>
            @endif
            <div class="phone-status" aria-hidden="true">
                <span>09:41</span>
                <span class="phone-status-icons">
                    <svg width="15" height="12" viewBox="0 0 15 12" fill="currentColor"><rect x="0" y="8" width="3" height="4" rx=".5"/><rect x="4" y="5" width="3" height="7" rx=".5"/><rect x="8" y="2" width="3" height="10" rx=".5"/><rect x="12" y="0" width="3" height="12" rx=".5"/></svg>
                    <svg width="16" height="12" viewBox="0 0 16 12" fill="currentColor"><path d="M8 3.2c1.8 0 3.4.7 4.6 1.8l1.2-1.3A8.4 8.4 0 0 0 8 .8 8.4 8.4 0 0 0 2.2 3.7l1.2 1.3A6.6 6.6 0 0 1 8 3.2Zm0 3.2c.9 0 1.7.3 2.3.9l1.2-1.3A5 5 0 0 0 8 4.8a5 5 0 0 0-3.5 1.2l1.2 1.3c.6-.6 1.4-.9 2.3-.9ZM8 9.6l2-2.1a2.8 2.8 0 0 0-4 0L8 9.6Z"/></svg>
                    <svg width="24" height="12" viewBox="0 0 24 12"><rect x="0.5" y="0.5" width="20" height="11" rx="2" fill="none" stroke="currentColor"/><rect x="2" y="2" width="14" height="8" rx="1" fill="currentColor"/><rect x="21.5" y="3.5" width="1.5" height="5" rx=".4" fill="currentColor"/></svg>
                </span>
            </div>

            @if ($pushed)
                <div class="phone-bar">
                    <a class="phone-back" href="{{ $backHref ?? $open($back[0]) }}" @unless ($ios) aria-label="Back" @endunless>
                        <svg width="12" height="20" viewBox="0 0 12 20" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M10 2 2 10l8 8"/></svg>
                        @if ($ios)
                            <span>{{ $back[1] }}</span>
                        @endif
                    </a>
                    <strong>{{ $bar }}</strong>
                    <span class="phone-bar-end"></span>
                </div>
            @endif

            <div class="phone-body">
                {{ $slot }}
            </div>

            @if ($showTabs)
                <nav class="phone-tabs" aria-label="{{ $ios ? 'iPhone' : 'Android' }}">
                    <a href="{{ $open('today') }}" @class(['on' => $tab === 'today'])>
                        <x-mockups.icon name="calendar" :solid="$tab === 'today'" />
                        <span>Matches</span>
                    </a>
                    <a href="{{ $open('find') }}" @class(['on' => $tab === 'find'])>
                        <x-mockups.icon name="search" :solid="$tab === 'find'" />
                        <span>Find</span>
                    </a>
                    <a href="{{ $open('suppliers') }}" @class(['on' => $tab === 'suppliers'])>
                        <x-mockups.icon name="store" :solid="$tab === 'suppliers'" />
                        <span>Suppliers</span>
                    </a>
                    <a href="{{ $open('you') }}" @class(['on' => $tab === 'you'])>
                        <x-mockups.icon name="user" :solid="$tab === 'you'" />
                        <span>You</span>
                    </a>
                </nav>
            @endif
            <div class="phone-home" aria-hidden="true"></div>
        </div>
    </div>
    <figcaption>{{ $ios ? 'iPhone · App Store' : 'Android · Google Play' }}</figcaption>
</figure>
