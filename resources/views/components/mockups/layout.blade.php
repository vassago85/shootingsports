@props([
    'title' => null,
    'description' => null,
    'active' => null,
    'sample' => null,
])
<!DOCTYPE html>
<html lang="en-ZA" @class(['mk-device-mobile' => request('device') === 'mobile'])>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>{{ $title ? $title.' — ShootingSports mockup' : 'ShootingSports mockup' }}</title>
    @if ($description)
        <meta name="description" content="{{ $description }}">
    @endif
    <link rel="icon" type="image/svg+xml" href="{{ asset('favicon.svg') }}">
    <meta name="theme-color" content="#1B1F27">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Saira+Condensed:wght@500;600;700&family=IBM+Plex+Mono:wght@400;500;600&family=IBM+Plex+Sans:wght@400;500;600&family=Roboto:wght@400;500;700&display=swap">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <style>{!! file_get_contents(resource_path('css/mockups.css')) !!}</style>
    @stack('head')
</head>
<body class="mk-public">
    <a class="skip" href="#main">Skip to content</a>
    <div class="mk-ribbon">
        <span>Redesign mockup</span>
        <a href="{{ $mk('mockups.index', [], false) }}">Index</a>
        @if ($sample)
            <span>Sample: {{ $sample }}</span>
        @endif
        <span class="sp">
            <a href="{{ $mk(request()->route()->getName(), request()->route()->parameters(), false) }}">Desktop</a>
            <a href="{{ $mk(request()->route()->getName(), array_merge(request()->route()->parameters(), request()->except('device', 'page'), ['device' => 'mobile']), false) }}">Mobile</a>
        </span>
    </div>
    <header class="nav">
        <div class="nav-in">
            <a class="brand" href="{{ $mk('mockups.home') }}">
                <svg class="mark" width="28" height="28" viewBox="0 0 40 40" role="img" aria-label="Reticle mark">
                    <circle cx="20" cy="20" r="17" fill="none" stroke="#FFFFFF" stroke-width="1.6"/>
                    <circle cx="20" cy="20" r="7.5" fill="none" stroke="#FFFFFF" stroke-width="1"/>
                    <path d="M20 1v11M20 28v11M1 20h11M28 20h11" stroke="#FFFFFF" stroke-width="1.6"/>
                    <circle cx="20" cy="20" r="2" fill="#6B7D3A"/>
                </svg>
                <span class="brand-text">
                    <span class="wordmark">ShootingSports</span>
                    <span class="sub">The SA Register</span>
                </span>
            </a>
            <nav class="nav-links" aria-label="Primary">
                <a href="{{ $mk('mockups.matches') }}" @class(['on' => $active === 'matches'])>Matches</a>
                <a href="{{ $mk('mockups.sports') }}" @class(['on' => $active === 'sports'])>Sports</a>
                <a href="{{ $mk('mockups.clubs') }}" @class(['on' => $active === 'clubs'])>Clubs</a>
                <a href="{{ $mk('mockups.ranges') }}" @class(['on' => $active === 'ranges'])>Ranges</a>
                <a href="{{ $mk('mockups.industry') }}" @class(['on' => $active === 'industry'])>Industry</a>
            </nav>
            <a class="mk-search" href="{{ $mk('mockups.search') }}" aria-label="Search">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                    <circle cx="11" cy="11" r="7"/><path d="M20 20l-3.5-3.5"/>
                </svg>
            </a>
            <div class="nav-cta">
                <a class="btn ghost on-dark" href="{{ $mk('mockups.account') }}">Sign in</a>
                <a class="btn" href="{{ $mk('mockups.manage.matches.new') }}">List an event</a>
            </div>
        </div>
    </header>

    <main id="main">
        {{ $slot }}
    </main>

    <footer class="mk-foot">
        <div class="wrap mk-foot-grid">
            <div>
                <strong class="wordmark" style="font-family:var(--f-display);text-transform:uppercase;color:#F1F2EE">ShootingSports</strong>
                <p>Matches, clubs, ranges and shooting sports across South Africa.</p>
            </div>
            <div>
                <h4>Browse</h4>
                <ul>
                    <li><a href="{{ $mk('mockups.matches') }}">Matches</a></li>
                    <li><a href="{{ $mk('mockups.sports') }}">Sports</a></li>
                    <li><a href="{{ $mk('mockups.clubs') }}">Clubs</a></li>
                    <li><a href="{{ $mk('mockups.ranges') }}">Ranges</a></li>
                    <li><a href="{{ $mk('mockups.industry') }}">Industry</a></li>
                </ul>
            </div>
            <div>
                <h4>Account</h4>
                <ul>
                    <li><a href="{{ $mk('mockups.account') }}">My Shooting</a></li>
                    <li><a href="{{ $mk('mockups.onboarding') }}">Set up follows</a></li>
                    <li><a href="{{ route('login') }}">Sign in</a></li>
                    <li><a href="{{ route('register') }}">Create account</a></li>
                </ul>
            </div>
            <div>
                <h4>Organisers</h4>
                <ul>
                    <li><a href="{{ $mk('mockups.manage.index') }}">Club desk</a></li>
                    <li><a href="{{ $mk('mockups.manage.matches.new') }}">List an event</a></li>
                    <li><a href="{{ route('claim') }}">List your club</a></li>
                    <li><a href="{{ route('advertise') }}">Advertise</a></li>
                </ul>
            </div>
        </div>
    </footer>

    <nav class="mk-tabbar" aria-label="Mobile">
        <a href="{{ $mk('mockups.home') }}" @class(['on' => $active === 'home'])>Home</a>
        <a href="{{ $mk('mockups.matches') }}" @class(['on' => $active === 'matches'])>Matches</a>
        <a href="{{ $mk('mockups.sports') }}" @class(['on' => $active === 'sports'])>Sports</a>
        <a href="{{ $mk('mockups.account') }}" @class(['on' => $active === 'account'])>My Shooting</a>
        <details>
            <summary>More</summary>
            <a href="{{ $mk('mockups.manage.matches.new') }}">List an event</a>
            <a href="{{ $mk('mockups.search') }}">Search</a>
            <a href="{{ $mk('mockups.industry') }}">Industry</a>
            <a href="{{ $mk('mockups.ranges') }}">Ranges</a>
            <a href="{{ $mk('mockups.clubs') }}">Clubs</a>
        </details>
    </nav>

    <script>
        document.addEventListener('click', (event) => {
            const nearLink = event.target.closest('[data-near-link]');
            if (nearLink && navigator.geolocation) {
                event.preventDefault();
                navigator.geolocation.getCurrentPosition((pos) => {
                    const url = new URL(nearLink.href, window.location.origin);
                    url.searchParams.set('lat', String(pos.coords.latitude));
                    url.searchParams.set('lng', String(pos.coords.longitude));
                    url.searchParams.set('radius', '150');
                    url.searchParams.set('sort', 'closest');
                    window.location.assign(url.toString());
                });
            }
            const locate = event.target.closest('[data-app-near]');
            if (locate && navigator.geolocation) {
                event.preventDefault();
                navigator.geolocation.getCurrentPosition((pos) => {
                    const url = new URL(locate.getAttribute('data-app-near'), window.location.origin);
                    url.searchParams.set('lat', String(pos.coords.latitude));
                    url.searchParams.set('lng', String(pos.coords.longitude));
                    window.location.assign(url.toString());
                }, () => {
                    window.location.assign(locate.href);
                });
            }
            const follow = event.target.closest('[data-follow]');
            if (follow) {
                const on = follow.classList.toggle('is-on');
                follow.setAttribute('aria-pressed', on ? 'true' : 'false');
                follow.textContent = on ? 'Following' : (follow.dataset.follow || 'Follow');
            }
            const share = event.target.closest('[data-share]');
            if (share && navigator.clipboard) {
                navigator.clipboard.writeText(share.dataset.share).then(() => {
                    share.textContent = 'Link copied';
                });
            }
        });
        document.addEventListener('submit', (event) => {
            const form = event.target;
            if (!form.matches('[data-finder]')) return;
            const province = form.querySelector('[name="province"]');
            if (!province || province.value !== 'near' || !navigator.geolocation) return;
            event.preventDefault();
            navigator.geolocation.getCurrentPosition((pos) => {
                const url = new URL(form.action, window.location.origin);
                new FormData(form).forEach((value, key) => {
                    if (key !== 'province' && value !== '') url.searchParams.set(key, value);
                });
                url.searchParams.set('lat', String(pos.coords.latitude));
                url.searchParams.set('lng', String(pos.coords.longitude));
                url.searchParams.set('radius', '150');
                url.searchParams.set('sort', 'closest');
                window.location.assign(url.toString());
            }, () => form.submit());
        });
    </script>
    @stack('scripts')
</body>
</html>
