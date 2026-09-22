@if ($screen === 'data')
    <div class="app-pad">
        <h1 class="app-hero">Your data</h1>
        <p class="app-lead">We do not sell personal information. Here is what the app holds, why, and how you take it back.</p>
        <div class="app-stack">
            <a class="app-btn" href="{{ $app('data', ['saved' => 1]) }}">Download my data</a>
            <a class="app-btn secondary" href="{{ $app('delete') }}">Delete account</a>
        </div>
        @if ($saved)
            <p class="app-banner">In the real app this sends a file of your account, follows, searches and log.</p>
        @endif
        <p class="app-sub">What we store</p>
        <ul class="app-list">
            <li><span>Account</span><small>Name, email and a hashed password, so you can sign in.</small></li>
            <li><span>Follows</span><small>Sports, clubs and a province. Free accounts: {{ $freeFollows }} follows.</small></li>
            <li><span>Saved searches</span><small>Free: {{ $freeSearches }}. Pro: no cap.</small></li>
            <li><span>Shooting log</span><small>Matches you mark as shot. Free: {{ $freeLog }} entries.</small></li>
            <li><span>Subscription</span><small>Whether Pro is active and the renewal date, from {{ $store }}. Card numbers stay with the store.</small></li>
            <li><span>Location</span><small>The province on your account. A one-time pin only if you tap Near me — it isn't stored.</small></li>
        </ul>
        <p class="app-sub">What we don't collect</p>
        <ul class="app-list plain">
            <li>Advertising ID, contacts, photos or microphone.</li>
            <li>A list of firearms you own.</li>
            <li>Browsing sold to advertisers.</li>
        </ul>
        <p class="app-sub">Who can see it</p>
        <ul class="app-list plain">
            <li>You, and ShootingSports to run the register.</li>
            <li>{{ $store }}, for the subscription only.</li>
            <li>No one else. No advertising network.</li>
        </ul>
        <p class="app-lead">Private rows stay while the account exists. Delete the account and they're erased. Under POPIA you can ask for access, a correction or deletion here, or at <a href="mailto:hello@shootingsports.co.za">hello@shootingsports.co.za</a>.</p>
        <p class="app-fine"><a href="{{ route('privacy') }}">Privacy policy</a> · <a href="{{ route('terms') }}">Terms of use</a></p>
    </div>

@elseif ($screen === 'delete')
    <div class="app-pad">
        <h1 class="app-hero">Delete your account?</h1>
        <p class="app-lead">This removes the private account. It can't be undone from the app.</p>
        <ul class="app-list plain">
            <li>Name, email and password hash.</li>
            <li>Follows, saved searches and the shooting log.</li>
            <li>Alert settings and this phone's push token.</li>
        </ul>
        <div class="app-legal">
            <p>Deleting the account does not cancel a store subscription. {{ $store }} keeps charging until you cancel. Cancel Pro first.</p>
        </div>
        <div class="app-stack">
            <a class="app-btn" href="{{ $app('cancel') }}">Cancel subscription first</a>
            <a class="app-btn secondary" href="{{ $app('data') }}">Download my data</a>
            <a class="app-btn danger" href="{{ $app('deleted') }}">Delete my account</a>
            <a class="app-btn quiet" href="{{ $app('you') }}">Keep my account</a>
        </div>
        <p class="app-fine">You can also email <a href="mailto:hello@shootingsports.co.za">hello@shootingsports.co.za</a> and ask for the account to be deleted.</p>
    </div>

@elseif ($screen === 'deleted')
    <div class="app-pad">
        <p class="app-kicker">Preview</p>
        <h1 class="app-hero">Account deleted</h1>
        <p class="app-lead">The private account would be gone. Public match listings a club published stay on the register.</p>
        <p class="app-lead">If a store subscription was still active, cancel it in {{ $store }} so the charges stop.</p>
        <a class="app-btn" href="{{ $app('signin') }}">Done</a>
    </div>

@elseif ($screen === 'permissions')
    <div class="app-pad">
        <h1 class="app-hero">Near me</h1>
        <p class="app-lead">Location is used once, to show what's close to you. We don't save where you've been, and you can say no.</p>
        <div class="app-stack">
            <a class="app-btn" href="{{ $app('home', ['near' => 'denied']) }}" data-app-near="{{ $app('home', ['near' => '1', 'km' => 100]) }}">Use my location once</a>
            <a class="app-btn secondary" href="{{ $app('home') }}">Not now</a>
        </div>
        <p class="app-sub">Match alerts</p>
        <p class="app-lead">A notification when a club or sport you follow adds a match. Off until you allow it.</p>
        <div class="app-stack">
            <a class="app-btn" href="{{ $app('alerts') }}">Allow alerts</a>
            <a class="app-btn secondary" href="{{ $app('home') }}">Not now</a>
        </div>
    </div>

@elseif ($screen === 'signin')
    <div class="app-welcome">
        <div class="app-welcome-mark"><x-mockups.icon name="target" /></div>
        <h1 class="app-welcome-hero">Find somewhere to shoot.</h1>
        <p class="app-welcome-sub">Matches, clubs and ranges across South Africa. An account is optional.</p>
        <div class="app-welcome-actions">
            @if ($ios)
                <a class="app-btn apple" href="{{ $app('you') }}">Sign in with Apple</a>
            @else
                <a class="app-btn google" href="{{ $app('you') }}">Continue with Google</a>
            @endif
            <a class="app-btn secondary" href="{{ $app('you') }}">Continue with email</a>
            <a class="app-textlink center" href="{{ $app('home') }}">Explore without an account</a>
        </div>
        <p class="app-welcome-legal">By continuing you agree to the <a href="{{ route('terms') }}">Terms</a> and <a href="{{ route('privacy') }}">Privacy policy</a>. You must be 18 or older.</p>
    </div>
@endif
