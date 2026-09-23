@props(['title' => 'ShootingSports', 'active' => 'home', 'description' => null])
@php
    $dark = request()->query('theme') === 'dark';
    $themeParams = request()->except(['theme', 'page']);
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title }} — ShootingSports</title>
    @vite(['resources/css/app.css'])
    <style>{!! file_get_contents(resource_path('css/mockups-v2.css')) !!}</style>
    @if ($description)
        <meta name="description" content="{{ $description }}">
    @endif
    <script>
        const cartoKey = @json($cartoApiKey);
        const cartoStyle = @json($dark ? 'dark_all' : 'light_all');
        function v2BaseMap(map) {
            const osm = function () {
                L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                    attribution: '&copy; OpenStreetMap',
                    maxZoom: 19
                }).addTo(map);
            };
            if (!cartoKey || location.hostname !== 'shootingsports.co.za') {
                osm();
                return;
            }
            const tiles = L.tileLayer('https://{s}.basemaps.cartocdn.com/' + cartoStyle + '/{z}/{x}/{y}{r}.png?key=' + encodeURIComponent(cartoKey), {
                attribution: '&copy; OpenStreetMap &copy; CARTO',
                subdomains: 'abcd',
                maxZoom: 19
            });
            let failed = 0;
            tiles.on('tileerror', function () {
                failed += 1;
                if (failed === 1) {
                    map.removeLayer(tiles);
                    osm();
                }
            });
            tiles.addTo(map);
        }
    </script>
</head>
<body @class(['v2', 'v2-dark' => $dark])>
    <div class="v2-ribbon">
        <a href="{{ $mk('mockups.index', [], false) }}">Index</a>
        <a href="{{ $mk(request()->route()->getName(), array_merge($themeParams, ['theme' => 'light']), false) }}" @class(['on' => ! $dark])>Light</a>
        <a href="{{ $mk(request()->route()->getName(), array_merge($themeParams, ['theme' => 'dark']), false) }}" @class(['on' => $dark])>Dark</a>
    </div>
    <header class="v2-header">
        <div class="v2-header-in">
            <a class="v2-brand" href="{{ $mk('mockups.v2.home') }}">
                <svg width="28" height="28" viewBox="0 0 40 40" aria-hidden="true">
                    <circle cx="20" cy="20" r="17" fill="none" stroke="currentColor" stroke-width="1.6"/>
                    <circle cx="20" cy="20" r="7.5" fill="none" stroke="currentColor" stroke-width="1"/>
                    <path d="M20 1v11M20 28v11M1 20h11M28 20h11" stroke="currentColor" stroke-width="1.6"/>
                    <circle cx="20" cy="20" r="2" fill="#c5e07a"/>
                </svg>
                <span class="v2-brand-text">
                    <strong>ShootingSports</strong>
                    <span>The SA Register</span>
                </span>
            </a>
            <nav class="v2-nav" aria-label="Primary">
                <a href="{{ $mk('mockups.v2.matches') }}" @class(['on' => $active === 'matches'])>Matches</a>
                <a href="{{ $mk('mockups.v2.sports') }}" @class(['on' => $active === 'sports'])>Sports</a>
                <a href="{{ $mk('mockups.v2.clubs') }}" @class(['on' => $active === 'clubs'])>Clubs</a>
                <a href="{{ $mk('mockups.v2.ranges') }}" @class(['on' => $active === 'ranges'])>Ranges</a>
                <a href="{{ $mk('mockups.v2.industry') }}" @class(['on' => $active === 'industry'])>Industry</a>
            </nav>
            <div class="v2-tools">
                <a class="v2-iconbtn" href="{{ $mk('mockups.v2.search') }}" aria-label="Search">
                    <x-mockups.v2.icon name="search" />
                </a>
                <a class="v2-btn v2-btn-ghost" href="{{ $mk('mockups.account') }}"><span>Sign in</span></a>
                <a class="v2-btn v2-btn-lime" href="{{ $mk('mockups.manage.matches.new') }}">List an event</a>
            </div>
        </div>
    </header>
    <main id="main">
        {{ $slot }}
    </main>
</body>
</html>
