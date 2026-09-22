<x-mockups.layout title="iOS and Android apps" active="account">
    @php
        $theme = request()->query('theme') === 'light' ? 'light' : 'dark';
        $large = request()->query('type') === 'large';
        $app = function (string $target, array $extra = []) use ($mk, $paused, $theme, $large): string {
            $params = [
                'screen' => $target,
                'theme' => $extra['theme'] ?? $theme,
            ];
            unset($extra['theme']);

            if (($extra['type'] ?? null) === 'default') {
                unset($extra['type']);
            } elseif (($extra['type'] ?? null) === 'large' || ($large && ! array_key_exists('type', $extra))) {
                $params['type'] = 'large';
                unset($extra['type']);
            }

            if (($extra['emails'] ?? null) === 'on') {
                unset($extra['emails']);
            } elseif ($paused && $target === 'alerts' && ! array_key_exists('emails', $extra)) {
                $params['emails'] = 'off';
            }

            $sportFamilies = ['rifle', 'handgun', 'shotgun', 'airgun', 'multi'];

            if (array_key_exists('family', $extra)) {
                $chosen = is_string($extra['family']) ? $extra['family'] : '';
                unset($extra['family']);

                if (in_array($chosen, $sportFamilies, true)) {
                    $params['family'] = $chosen;
                }
            } elseif (in_array($target, ['sports', 'sport'], true) && in_array(request()->string('family')->toString(), $sportFamilies, true)) {
                $params['family'] = request()->string('family')->toString();
            }

            if (array_key_exists('q', $extra)) {
                $typed = trim((string) $extra['q']);
                unset($extra['q']);

                if ($typed !== '') {
                    $params['q'] = $typed;
                }
            } elseif (in_array($target, ['sports', 'sport'], true)) {
                $typed = trim(request()->string('q')->toString());

                if ($typed !== '') {
                    $params['q'] = $typed;
                }
            }

            if (array_key_exists('month', $extra)) {
                $chosen = is_string($extra['month']) ? $extra['month'] : '';
                unset($extra['month']);

                if (preg_match('/^\d{4}-\d{2}$/', $chosen)) {
                    $params['month'] = $chosen;
                }
            } elseif ($target === 'calendar' && preg_match('/^\d{4}-\d{2}$/', request()->string('month')->toString())) {
                $params['month'] = request()->string('month')->toString();
            }

            if (array_key_exists('day', $extra)) {
                $chosen = is_string($extra['day']) ? $extra['day'] : '';
                unset($extra['day']);

                if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $chosen)) {
                    $params['day'] = $chosen;
                }
            } elseif ($target === 'calendar' && preg_match('/^\d{4}-\d{2}-\d{2}$/', request()->string('day')->toString())) {
                $params['day'] = request()->string('day')->toString();
            }

            if (array_key_exists('province', $extra)) {
                $chosen = is_string($extra['province']) ? $extra['province'] : '';
                unset($extra['province']);

                if ($chosen === 'all' || \App\Enums\Province::tryFrom($chosen) instanceof \App\Enums\Province) {
                    $params['province'] = $chosen;
                }
            } elseif (request()->query('province') === 'all' || \App\Enums\Province::tryFrom(request()->string('province')->toString()) instanceof \App\Enums\Province) {
                $params['province'] = request()->string('province')->toString();
            }

            if (array_key_exists('home', $extra)) {
                $chosen = is_string($extra['home']) ? $extra['home'] : '';
                unset($extra['home']);

                if (\App\Enums\Province::tryFrom($chosen) instanceof \App\Enums\Province) {
                    $params['home'] = $chosen;
                }
            } elseif (\App\Enums\Province::tryFrom(request()->string('home')->toString()) instanceof \App\Enums\Province) {
                $params['home'] = request()->string('home')->toString();
            }

            $clearPlace = array_key_exists('near', $extra) && ($extra['near'] === null || $extra['near'] === '' || $extra['near'] === '0');

            foreach (['near', 'lat', 'lng', 'km'] as $placeKey) {
                if (array_key_exists($placeKey, $extra)) {
                    $value = $extra[$placeKey];
                    unset($extra[$placeKey]);

                    if ($value !== null && $value !== '' && $value !== '0') {
                        $params[$placeKey] = $value;
                    }

                    continue;
                }

                if ($clearPlace || ($params['near'] ?? null) === 'denied') {
                    continue;
                }

                $current = request()->query($placeKey);

                if ($placeKey === 'near' && $current === '1') {
                    $params['near'] = '1';
                } elseif ($placeKey === 'km' && in_array((int) $current, [50, 100, 150], true) && request()->has('km')) {
                    $params['km'] = (int) $current;
                } elseif (in_array($placeKey, ['lat', 'lng'], true) && is_numeric($current) && request()->query('near') === '1') {
                    $params[$placeKey] = $current;
                }
            }

            foreach ($extra as $key => $value) {
                if (is_array($value)) {
                    $value = array_values(array_filter($value, fn (mixed $item): bool => $item !== null && $item !== ''));

                    if ($value === []) {
                        continue;
                    }
                } elseif ($value === null || $value === '') {
                    continue;
                }

                $params[$key] = $value;
            }

            return $mk('mockups.apps', $params);
        };

        // Find the label of the current screen for the canvas header.
        $currentLabel = 'Screen';
        $groupLabel = '';
        foreach ($journey as $group) {
            foreach ($group['screens'] as $item) {
                if ($item['key'] === $screen) {
                    $currentLabel = $item['label'];
                    $groupLabel = $group['group'];
                }
            }
        }
    @endphp

    <div class="wrap app-page">
        <header class="mk-pagehead">
            <p class="label">Native app concept</p>
            <h1>ShootingSports on iPhone &amp; Android</h1>
            <p class="mk-lede">Review the app in user-journey order — from splash to daily use. The sidebar walks left-to-right through what a real user experiences. Screens open in both iPhone and Android chrome. Dark mode is default; toggle Light to see the warm off-white surface.</p>
        </header>

        <div class="app-shell">
            <aside class="app-sidebar" aria-label="App journey">
                @php $counter = 0; @endphp
                @foreach ($journey as $group)
                    <h3>{{ $group['group'] }}</h3>
                    @foreach ($group['screens'] as $item)
                        @php $counter++; @endphp
                        <a href="{{ $app($item['key']) }}" @class(['on' => $screen === $item['key']])>
                            <span class="app-side-n">{{ str_pad((string) $counter, 2, '0', STR_PAD_LEFT) }}</span>
                            <span>{{ $item['label'] }}</span>
                        </a>
                    @endforeach
                @endforeach
            </aside>

            <div class="app-canvas">
                <div class="app-canvas-controls">
                    <div class="app-canvas-title">
                        <span>{{ $groupLabel }}</span>
                        {{ $currentLabel }}
                    </div>
                    <div class="app-appearance" role="group" aria-label="Appearance">
                        <a href="{{ $app($screen, ['theme' => 'dark']) }}" @class(['on' => $theme === 'dark'])>Dark</a>
                        <a href="{{ $app($screen, ['theme' => 'light']) }}" @class(['on' => $theme === 'light'])>Light</a>
                        <a href="{{ $app($screen, ['type' => $large ? 'default' : 'large']) }}">{{ $large ? 'Default text' : 'Larger text' }}</a>
                    </div>
                </div>

                @foreach (['ios', 'android'] as $platform)
                    <x-mockups.phone :platform="$platform" :screen="$screen">
                        @include('mockups.partials.app-router')
                    </x-mockups.phone>
                @endforeach
            </div>
        </div>

        <section class="mk-section app-store-notes">
            <h2>What a store review looks for</h2>
            <p class="mk-support">Both stores need these in place. Each link jumps to the screen that carries the compliance surface.</p>
            <div class="app-notes">
                <article>
                    <h3>App Store</h3>
                    <ul>
                        <li><a href="{{ $app('plans') }}">Subscription name, length and price before purchase</a></li>
                        <li><a href="{{ $app('plans') }}">Privacy policy and Terms of Use on that same screen</a></li>
                        <li><a href="{{ $app('subscription') }}">Restore purchases</a></li>
                        <li><a href="{{ $app('you') }}">Cancel in the app, plus a link to Apple Subscriptions</a></li>
                        <li><a href="{{ $app('delete') }}">Delete account in the app</a></li>
                        <li><a href="{{ $app('signin') }}">Sign in with Apple</a></li>
                        <li><a href="{{ $app('permissions') }}">Why location or alerts are needed, before the system prompt</a></li>
                        <li><a href="{{ $app('signin') }}">18+ — a match register, not a firearm shop</a></li>
                    </ul>
                </article>
                <article>
                    <h3>Google Play</h3>
                    <ul>
                        <li><a href="{{ $app('plans') }}">Price, period and auto-renew before purchase</a></li>
                        <li><a href="{{ $app('cancel') }}">Cancel is as easy to find as subscribe</a></li>
                        <li><a href="{{ $app('subscription') }}">Link to Google Play subscriptions</a></li>
                        <li><a href="{{ $app('data') }}">What is collected, why, and who sees it</a></li>
                        <li><a href="{{ $app('delete') }}">Account and data deletion, with a web contact</a></li>
                        <li><a href="{{ $app('data') }}">Data is not sold</a></li>
                    </ul>
                </article>
            </div>
        </section>
    </div>
</x-mockups.layout>
