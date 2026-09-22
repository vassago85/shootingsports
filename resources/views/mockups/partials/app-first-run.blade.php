@if ($screen === 'splash')
    <div class="app-splash">
        <div class="app-splash-mark"><x-mockups.icon name="target" /></div>
        <strong>ShootingSports</strong>
        <em>South Africa's shooting sports register.</em>
    </div>

@elseif ($screen === 'welcome')
    <div class="app-welcome">
        <div class="app-welcome-mark"><x-mockups.icon name="target" /></div>
        <h1 class="app-welcome-hero">Find somewhere to shoot.</h1>
        <p class="app-welcome-sub">Matches, clubs, ranges and shooting sports across South Africa.</p>
        <div class="app-welcome-actions">
            <a class="app-btn" href="{{ $app('location') }}">Get started</a>
            <a class="app-btn secondary" href="{{ $app('signin') }}">I already have an account</a>
            <a class="app-textlink center" href="{{ $app('home') }}">Explore without an account</a>
        </div>
        <p class="app-welcome-legal"><a href="{{ route('terms') }}">Terms</a> · <a href="{{ route('privacy') }}">Privacy</a></p>
    </div>

@elseif ($screen === 'location')
    <div class="app-onboard">
        <div class="app-onboard-progress" aria-hidden="true"><i class="on"></i><i></i><i></i><i></i><i></i></div>
        <div class="app-onboard-body">
            <h1 class="app-onboard-hero">What's near you?</h1>
            <p class="app-onboard-lead">We'll use this to show matches, clubs and ranges nearby. Your exact location is optional.</p>
            <a class="app-lo" href="{{ $app('sports-choose') }}" data-app-near="{{ $app('sports-choose', ['near' => '1', 'km' => 100]) }}">
                <x-mockups.icon name="pin" />
                <span>
                    <strong>Use my location</strong>
                    <em>We'll ask the phone only after you tap this.</em>
                </span>
            </a>
            <p class="app-eyebrow" style="margin: 18px 0 10px;">or choose a province</p>
            <div class="app-chipbar" role="group" aria-label="Province" style="margin: 0 -20px;">
                @foreach (\App\Enums\Province::cases() as $province)
                    <a class="app-chip" href="{{ $app('sports-choose', ['home' => $province->value]) }}">{{ $province->getLabel() }}</a>
                @endforeach
            </div>
        </div>
        <div class="app-onboard-actions">
            <a class="app-textlink center" href="{{ $app('sports-choose') }}">Skip for now</a>
        </div>
    </div>

@elseif ($screen === 'sports-choose')
    @php
        $chosen = $onboarding['sports'] ?? collect();
        $toggle = function (string $slug) use ($app, $chosen): string {
            $next = $chosen->contains($slug)
                ? $chosen->reject(fn (string $item): bool => $item === $slug)
                : $chosen->merge([$slug]);

            return $app('sports-choose', ['choose' => $next->values()->all()]);
        };
    @endphp
    <div class="app-onboard">
        <div class="app-onboard-progress" aria-hidden="true"><i class="on"></i><i class="on"></i><i></i><i></i><i></i></div>
        <div class="app-onboard-body">
            <h1 class="app-onboard-hero">What do you shoot?</h1>
            <p class="app-onboard-lead">Choose as many as you like. You can change this later.</p>
            @foreach ($onboarding['sports_by_family'] ?? [] as $group)
                <p class="app-sub">{{ $group['label'] }}</p>
                <div class="app-tilegrid">
                    @foreach ($group['items'] as $sport)
                        <a href="{{ $toggle($sport['slug']) }}" @class(['app-tile', 'on' => $chosen->contains($sport['slug'])])>
                            <x-mockups.icon :name="$familyIcon($sport['family'])" />
                            <span>{{ $sport['name'] }}</span>
                        </a>
                    @endforeach
                </div>
            @endforeach
        </div>
        <div class="app-onboard-actions">
            @if ($chosen->isNotEmpty())
                <p class="app-onboard-count">{{ $chosen->count() }} selected</p>
            @endif
            <a class="app-btn" href="{{ $app('follow-onboard', ['choose' => $chosen->all()]) }}">Continue</a>
        </div>
    </div>

@elseif ($screen === 'follow-onboard')
    @php
        $followed = $onboarding['clubs'] ?? collect();
        $toggleClub = function (string $slug) use ($app, $followed, $onboarding): string {
            $next = $followed->contains($slug)
                ? $followed->reject(fn (string $item): bool => $item === $slug)
                : $followed->merge([$slug]);

            return $app('follow-onboard', [
                'choose' => ($onboarding['sports'] ?? collect())->all(),
                'follow' => $next->values()->all(),
            ]);
        };
    @endphp
    <div class="app-onboard">
        <div class="app-onboard-progress" aria-hidden="true"><i class="on"></i><i class="on"></i><i class="on"></i><i></i><i></i></div>
        <div class="app-onboard-body">
            <h1 class="app-onboard-hero">Follow your shooting</h1>
            <p class="app-onboard-lead">Clubs and series that match what you shoot. Nothing is required.</p>
            @forelse ($onboarding['suggested_clubs'] ?? [] as $club)
                <a class="app-row" href="{{ $toggleClub($club['slug']) }}">
                    <span class="app-row-main">
                        <span class="app-mark"><x-mockups.icon name="users" /></span>
                        <span>
                            <strong>{{ $club['name'] }}</strong>
                            <em>{{ collect(array_slice($club['disciplines'] ?? [], 0, 2))->implode(' · ') ?: ($club['place'] ?? '') }}</em>
                        </span>
                    </span>
                    <span @class(['app-chip', 'on' => $followed->contains($club['slug'])])>{{ $followed->contains($club['slug']) ? 'Following' : 'Follow' }}</span>
                </a>
            @empty
                <p class="app-empty">No clubs to suggest yet. You can follow them later.</p>
            @endforelse
        </div>
        <div class="app-onboard-actions">
            <a class="app-btn" href="{{ $app('alerts-onboard', ['choose' => ($onboarding['sports'] ?? collect())->all(), 'follow' => $followed->all()]) }}">Continue</a>
            <a class="app-textlink center" href="{{ $app('alerts-onboard') }}">Skip</a>
        </div>
    </div>

@elseif ($screen === 'alerts-onboard')
    <div class="app-onboard">
        <div class="app-onboard-progress" aria-hidden="true"><i class="on"></i><i class="on"></i><i class="on"></i><i class="on"></i><i></i></div>
        <div class="app-onboard-body">
            <h1 class="app-onboard-hero">Don't miss a match.</h1>
            <p class="app-onboard-lead">Turn on match alerts and we'll let you know when something changes for the clubs and sports you follow.</p>
            <ul class="app-activity">
                <li><x-mockups.icon name="flag" /><span><strong>New match added</strong><em>A club you follow lists a date.</em></span></li>
                <li><x-mockups.icon name="bell" /><span><strong>Registration closing</strong><em>Entry for a match you saved is ending.</em></span></li>
                <li><x-mockups.icon name="calendar" /><span><strong>Details changed</strong><em>A venue, time or round count moves.</em></span></li>
            </ul>
        </div>
        <div class="app-onboard-actions">
            <a class="app-btn" href="{{ $app('ready') }}">Enable alerts</a>
            <a class="app-textlink center" href="{{ $app('ready') }}">Not now</a>
        </div>
    </div>

@elseif ($screen === 'ready')
    @php
        $sportCount = ($onboarding['sports'] ?? collect())->count();
        $clubCount = ($onboarding['clubs'] ?? collect())->count();
    @endphp
    <div class="app-ready">
        <div class="app-ready-mark"><x-mockups.icon name="check" /></div>
        <h1>You're ready.</h1>
        <div class="app-ready-stats">
            <span>{{ $sportCount > 0 ? $sportCount : 3 }} sports followed</span>
            <span>{{ $clubCount > 0 ? $clubCount : 2 }} clubs followed</span>
            <span>{{ $close['home_place'] ?? 'Gauteng' }}</span>
        </div>
        <a class="app-btn" href="{{ $app('home') }}">See my shooting</a>
    </div>
@endif
