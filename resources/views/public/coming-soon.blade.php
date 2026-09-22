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
                <circle cx="200" cy="200" r="196" fill="none" stroke="#FFFFFF" stroke-width="1"/>
                <circle cx="200" cy="200" r="150" fill="none" stroke="#FFFFFF" stroke-width="1"/>
                <circle cx="200" cy="200" r="90" fill="none" stroke="#FFFFFF" stroke-width="1"/>
                <circle cx="200" cy="200" r="40" fill="none" stroke="#FFFFFF" stroke-width="1"/>
                <path d="M200 0v160M200 240v160M0 200h160M240 200h160" stroke="#FFFFFF" stroke-width="1"/>
                <path d="M180 240h40M170 260h60M160 280h80M180 160h40M170 140h60M160 120h80" stroke="#FFFFFF" stroke-width="0.8"/>
                <path d="M160 180v40M140 170v60M120 160v80M240 180v40M260 170v60M280 160v80" stroke="#FFFFFF" stroke-width="0.8"/>
                <circle class="cs-reticle-dot" cx="200" cy="200" r="3.5" fill="#6B7D3A"/>
            </svg>
        </div>

        <div class="cs-grid" aria-hidden="true"></div>

        <header class="cs-nav">
            <a class="cs-brand" href="{{ route('coming-soon') }}">
                <svg class="cs-mark" width="30" height="30" viewBox="0 0 40 40" role="img" aria-label="Reticle mark">
                    <circle cx="20" cy="20" r="17" fill="none" stroke="#FFFFFF" stroke-width="1.6"/>
                    <circle cx="20" cy="20" r="7.5" fill="none" stroke="#FFFFFF" stroke-width="1"/>
                    <path d="M20 1v11M20 28v11M1 20h11M28 20h11" stroke="#FFFFFF" stroke-width="1.6"/>
                    <circle cx="20" cy="20" r="2" fill="#6B7D3A"/>
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

            <section class="cs-interest" aria-labelledby="cs-interest-heading">
                <h2 id="cs-interest-heading" class="cs-interest-title">Help us load the register</h2>
                <p class="cs-interest-lede">
                    Have a range, run matches, belong to a club, or list a business?
                    Tell us — we'll confirm your email, then follow up.
                </p>

                @if (session('interest_status') === 'check_email')
                    <p class="cs-interest-success" role="status">
                        Check your email and click the confirmation link within 48 hours.
                        Until then your interest stays pending on our side.
                    </p>
                @else
                    @if ($errors->any())
                        <p class="cs-interest-error" role="alert">{{ $errors->first() }}</p>
                    @endif

                    <form
                        method="post"
                        action="{{ route('coming-soon.interest') }}"
                        class="cs-interest-form"
                        novalidate
                    >
                        @csrf
                        <input type="hidden" name="form_loaded_at" value="{{ time() }}">

                        <div class="cs-hp" aria-hidden="true">
                            <label for="company_website">Company website</label>
                            <input type="text" name="company_website" id="company_website" value="" tabindex="-1" autocomplete="off">
                        </div>

                        <label class="cs-field">
                            <span>I am / we are</span>
                            <select name="role" required>
                                <option value="" disabled @selected(old('role') === null)>Choose one…</option>
                                @foreach (\App\Enums\PrelaunchContributorRole::cases() as $role)
                                    <option value="{{ $role->value }}" @selected(old('role') === $role->value)>
                                        {{ $role->getLabel() }}
                                    </option>
                                @endforeach
                            </select>
                        </label>

                        <label class="cs-field">
                            <span>Your name</span>
                            <input type="text" name="name" value="{{ old('name') }}" required maxlength="120" autocomplete="name">
                        </label>

                        <label class="cs-field">
                            <span>Email</span>
                            <input type="email" name="email" value="{{ old('email') }}" required maxlength="255" autocomplete="email">
                        </label>

                        <label class="cs-field">
                            <span>Short note</span>
                            <textarea name="body" rows="3" required minlength="10" maxlength="1000" placeholder="Club / range / business name and how you’d like to help">{{ old('body') }}</textarea>
                        </label>

                        @if ($turnstileSiteKey = \App\Support\Turnstile::siteKey())
                            <div
                                class="cf-turnstile"
                                data-sitekey="{{ $turnstileSiteKey }}"
                                data-theme="dark"
                            ></div>
                            <script src="https://challenges.cloudflare.com/turnstile/v0/api.js" async defer></script>
                        @endif

                        <button type="submit" class="cs-btn">Send interest</button>
                    </form>
                @endif
            </section>

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
