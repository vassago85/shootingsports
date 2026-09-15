@props([
    'title' => null,
    'description' => null,
    'canonical' => null,
    'robots' => null,
    'image' => null,
    'jsonLd' => null,
])
<!DOCTYPE html>
<html lang="en-ZA">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <x-seo-meta
        :title="$title"
        :description="$description"
        :canonical="$canonical"
        :robots="$robots"
        :image="$image"
        :json-ld="$jsonLd"
    />
    <link rel="icon" type="image/svg+xml" href="{{ asset('favicon.svg') }}">
    <link rel="icon" type="image/x-icon" href="{{ asset('favicon.ico') }}" sizes="16x16 32x32 48x48">
    <link rel="apple-touch-icon" sizes="180x180" href="{{ asset('apple-touch-icon.png') }}">
    <meta name="theme-color" content="#1B1F27">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Saira+Condensed:wght@500;600;700&family=IBM+Plex+Mono:wght@400;500;600&family=IBM+Plex+Sans:wght@400;500;600&display=swap">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
</head>
<body>
    <a class="skip" href="#main">Skip to content</a>
    <header class="nav">
        <div class="nav-in">
            <a class="brand" href="{{ route('home') }}">
                <svg class="mark" width="28" height="28" viewBox="0 0 40 40" role="img" aria-label="Reticle mark">
                    <circle cx="20" cy="20" r="17" fill="none" stroke="#D9AE52" stroke-width="1.6"/>
                    <circle cx="20" cy="20" r="7.5" fill="none" stroke="#D9AE52" stroke-width="1"/>
                    <path d="M20 1v11M20 28v11M1 20h11M28 20h11" stroke="#D9AE52" stroke-width="1.6"/>
                    <circle cx="20" cy="20" r="2" fill="#D9AE52"/>
                </svg>
                <span class="brand-text">
                    <span class="wordmark">ShootingSports</span>
                    <span class="sub">The SA Register</span>
                </span>
            </a>
            <nav class="nav-links" aria-label="Primary">
                <a href="{{ route('calendar') }}" class="{{ request()->routeIs('calendar') ? 'on' : '' }}">Calendar</a>
                <a href="{{ route('disciplines.index') }}" class="{{ request()->routeIs('disciplines.*') ? 'on' : '' }}">Discover</a>
                <a href="{{ route('clubs.index') }}" class="{{ request()->routeIs('clubs.*') || request()->routeIs('ranges.*') || request()->routeIs('suppliers.*') ? 'on' : '' }}">Directory</a>
                <a href="{{ route('claim') }}">For Clubs</a>
                <a href="{{ route('advertise') }}">Advertise</a>
            </nav>
            <div class="nav-cta">
                @auth
                    <a class="btn ghost on-dark" href="{{ route('my-calendar') }}">My calendar</a>
                    <a class="btn" href="{{ url('/desk') }}">Desk</a>
                @else
                    <a class="btn ghost on-dark" href="{{ url('/desk/login') }}">Director login</a>
                    <a class="btn" href="{{ url('/desk/register') }}">List your club</a>
                @endauth
            </div>
            <button class="hamburger" id="burger" type="button" aria-expanded="false" aria-controls="mobile-menu" aria-label="Open menu">Menu</button>
        </div>
        <nav class="mobile-menu" id="mobile-menu" aria-label="Mobile">
            <a href="{{ route('calendar') }}">Calendar</a>
            <a href="{{ route('disciplines.index') }}">Discover</a>
            <a href="{{ route('clubs.index') }}">Clubs</a>
            <a href="{{ route('ranges.index') }}">Ranges</a>
            <a href="{{ route('suppliers.index') }}">Industry</a>
            <a href="{{ route('claim') }}">For Clubs</a>
            @auth
                <a href="{{ route('my-calendar') }}">My calendar</a>
                <a href="{{ url('/desk') }}">Desk</a>
            @else
                <a href="{{ url('/desk/login') }}">Director login</a>
                <a href="{{ url('/desk/register') }}">List your club</a>
            @endauth
            <a href="{{ route('advertise') }}">Advertise</a>
        </nav>
    </header>

    {{ $slot }}

    <footer class="site">
        <div class="wrap">
            <div class="foot-grid">
                <div class="foot-about">
                    <a class="brand" href="{{ route('home') }}" style="margin-right:0">
                        <svg class="mark" width="28" height="28" viewBox="0 0 40 40" role="img" aria-label="Reticle mark">
                            <circle cx="20" cy="20" r="17" fill="none" stroke="#D9AE52" stroke-width="1.6"/>
                            <circle cx="20" cy="20" r="7.5" fill="none" stroke="#D9AE52" stroke-width="1"/>
                            <path d="M20 1v11M20 28v11M1 20h11M28 20h11" stroke="#D9AE52" stroke-width="1.6"/>
                            <circle cx="20" cy="20" r="2" fill="#D9AE52"/>
                        </svg>
                        <span class="brand-text">
                            <span class="wordmark">ShootingSports</span>
                            <span class="sub">The SA Register</span>
                        </span>
                    </a>
                    <p>An independent, neutral register of South African shooting sport. Every club and federation listed on the same terms, free of charge.</p>
                </div>
                <div>
                    <h4>Browse</h4>
                    <ul>
                        <li><a href="{{ route('calendar') }}">Match calendar</a></li>
                        <li><a href="{{ route('disciplines.index') }}">Discover</a></li>
                        <li><a href="{{ route('clubs.index') }}">Clubs</a></li>
                        <li><a href="{{ route('ranges.index') }}">Ranges</a></li>
                        <li><a href="{{ route('suppliers.index') }}">Industry</a></li>
                    </ul>
                </div>
                <div>
                    <h4>Participate</h4>
                    <ul>
                        <li><a href="{{ route('contact') }}">Contact</a></li>
                        @auth
                            <li><a href="{{ route('my-calendar') }}">My calendar</a></li>
                        @else
                            <li><a href="{{ url('/desk/login') }}">Director login</a></li>
                            <li><a href="{{ url('/desk/register') }}">Create a desk account</a></li>
                        @endauth
                        <li><a href="{{ route('claim') }}">For clubs</a></li>
                        <li><a href="{{ route('embed.docs') }}">Embed the calendar</a></li>
                    </ul>
                </div>
                <div>
                    <h4>About</h4>
                    <ul>
                        <li><a href="{{ route('advertise') }}">Advertise</a></li>
                        <li><a href="{{ route('privacy') }}">Privacy &amp; POPIA</a></li>
                    </ul>
                </div>
            </div>
            <div class="foot-bottom">
                <span>shootingsports.co.za</span>
                <span>Not a firearms dealer · no sales or transfers facilitated</span>
            </div>
        </div>
    </footer>
    @livewireScripts
</body>
</html>
