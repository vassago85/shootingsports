<x-layouts.coming-soon
    title="Coming soon"
    description="ShootingSports — the national register of South African shooting sport. Launching soon."
>
    {{--
        Coming-soon landing page shown while `config('coming-soon.enabled')`
        is true. Staff and match directors bypass the gate via
        EnsureComingSoonAccess middleware; everyone else lands here.

        The whole page is a single .coming-soon flex column so the hero
        stays vertically centred on any viewport. Reticle motif reuses
        the hero SVG from public/home.blade.php but is scoped under
        .cs-* classes so the two pages can evolve independently.
    --}}
    <div class="coming-soon">
        <div class="cs-reticle" aria-hidden="true">
            <svg viewBox="0 0 400 400" preserveAspectRatio="xMidYMid meet">
                <circle cx="200" cy="200" r="196" fill="none" stroke="#D9AE52" stroke-width="1"/>
                <circle cx="200" cy="200" r="150" fill="none" stroke="#D9AE52" stroke-width="1"/>
                <circle cx="200" cy="200" r="90" fill="none" stroke="#D9AE52" stroke-width="1"/>
                <circle cx="200" cy="200" r="40" fill="none" stroke="#D9AE52" stroke-width="1"/>
                <path d="M200 0v160M200 240v160M0 200h160M240 200h160" stroke="#D9AE52" stroke-width="1"/>
                <path d="M180 240h40M170 260h60M160 280h80M180 160h40M170 140h60M160 120h80" stroke="#D9AE52" stroke-width="0.8"/>
                <path d="M160 180v40M140 170v60M120 160v80M240 180v40M260 170v60M280 160v80" stroke="#D9AE52" stroke-width="0.8"/>
                <circle class="cs-reticle-dot" cx="200" cy="200" r="3.5" fill="#D9AE52"/>
            </svg>
        </div>

        <div class="cs-grid" aria-hidden="true"></div>

        <header class="cs-nav">
            <a class="cs-brand" href="{{ route('coming-soon') }}">
                <svg class="cs-mark" width="30" height="30" viewBox="0 0 40 40" role="img" aria-label="Reticle mark">
                    <circle cx="20" cy="20" r="17" fill="none" stroke="#D9AE52" stroke-width="1.6"/>
                    <circle cx="20" cy="20" r="7.5" fill="none" stroke="#D9AE52" stroke-width="1"/>
                    <path d="M20 1v11M20 28v11M1 20h11M28 20h11" stroke="#D9AE52" stroke-width="1.6"/>
                    <circle cx="20" cy="20" r="2" fill="#D9AE52"/>
                </svg>
                <span class="cs-brand-text">
                    <span class="cs-wordmark">ShootingSports</span>
                    <span class="cs-sub">The SA Register</span>
                </span>
            </a>
            <div class="cs-nav-cta">
                @auth
                    <span class="cs-hello">Signed in as {{ auth()->user()->name }}</span>
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button type="submit" class="cs-btn ghost">Log out</button>
                    </form>
                @else
                    <a class="cs-btn" href="{{ route('login') }}">Log in</a>
                @endauth
            </div>
        </header>

        <main class="cs-main" id="main">
            <p class="cs-label">
                <span class="cs-dot" aria-hidden="true"></span>
                Site build in progress
            </p>

            <h1 class="cs-headline">
                The national register of<br>
                South African <em>shooting sport</em>.
            </h1>

            <p class="cs-lede">
                One calendar. Every discipline. Every province. We're loading
                clubs, ranges and matches now — the doors open to shooters,
                directors and the public soon.
            </p>

            <dl class="cs-facts">
                <div>
                    <dt>Coverage</dt>
                    <dd>National · SA</dd>
                </div>
                <div>
                    <dt>Disciplines</dt>
                    <dd>Rifle · Pistol · Shotgun</dd>
                </div>
                <div>
                    <dt>Status</dt>
                    <dd>Pre-launch</dd>
                </div>
            </dl>

            @auth
                @if (! auth()->user()->is_staff && ! auth()->user()->is_match_director)
                    <p class="cs-note">
                        Thanks for signing up. The public site opens to shooters
                        at launch — we'll email you the moment it's live.
                    </p>
                @endif
            @else
                <p class="cs-cta">
                    Building the site? <a href="{{ route('login') }}">Log in</a>
                    to keep working behind the gate.
                </p>
            @endauth
        </main>

        <footer class="cs-foot">
            <span>&copy; {{ date('Y') }} ShootingSports</span>
            <span>shootingsports.co.za</span>
        </footer>
    </div>
</x-layouts.coming-soon>
