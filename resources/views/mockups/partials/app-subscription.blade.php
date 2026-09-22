@if ($screen === 'subscription')
    <div class="app-pad">
        @if ($started)
            <p class="app-banner">Trial started. Cancel before {{ $renews }} and you pay nothing.</p>
        @endif
        @if ($restored)
            <p class="app-banner">Purchases restored. Pro is on this {{ $ios ? 'Apple ID' : 'Google account' }}.</p>
        @endif
        <p class="app-kicker">ShootingSports Pro</p>
        <h1 class="app-hero">{{ $pricing['monthly']['display'] }}</h1>
        <p class="app-lead">Renews {{ $renews }}. Billed by {{ $billedBy }}. Cancel any time before then and the next charge doesn't happen.</p>
        <ul class="app-benefits">
            <li>Personalised match alerts</li>
            <li>Unlimited follows and saved searches</li>
            <li>Custom packing lists</li>
            <li>Full shooting log, PDF and CSV</li>
        </ul>
        <div class="app-stack">
            <a class="app-btn" href="{{ $app('cancel') }}">Cancel subscription</a>
            <a class="app-btn secondary" href="{{ $app('plans') }}">Change plan</a>
            <a class="app-btn secondary" href="{{ $app('subscription', ['restored' => 1]) }}">Restore purchases</a>
            <a class="app-btn quiet" href="{{ $manageUrl }}" target="_blank" rel="noopener">{{ $manageLabel }}</a>
        </div>
        <p class="app-fine">Cancelling stops future charges. Pro stays on until {{ $renews }}. Deleting the app does not cancel it.</p>
        <p class="app-fine"><a href="{{ route('privacy') }}">Privacy</a> · <a href="{{ route('terms') }}">Terms</a></p>
    </div>

@elseif ($screen === 'plans')
    <div class="app-pad">
        <p class="app-kicker">ShootingSports Pro</p>
        <h1 class="app-hero">{{ $plan['display'] }}</h1>
        <p class="app-lead">{{ $trialDays }} days free, then this price. The public match register stays free either way.</p>
        <div class="app-cycles">
            <a href="{{ $app('plans', ['cycle' => 'annual']) }}" @class(['on' => $cycle === 'annual'])>
                <b>Annual</b>
                <strong>{{ $pricing['annual']['display'] }}</strong>
                <em>{{ $pricing['annual']['summary'] }}</em>
            </a>
            <a href="{{ $app('plans', ['cycle' => 'monthly']) }}" @class(['on' => $cycle === 'monthly'])>
                <b>Monthly</b>
                <strong>{{ $pricing['monthly']['display'] }}</strong>
                <em>{{ $pricing['monthly']['summary'] }}</em>
            </a>
        </div>
        <dl class="app-facts">
            <div><dt>Service</dt><dd>ShootingSports Pro</dd></div>
            <div><dt>Length</dt><dd>{{ $length }}, auto-renews</dd></div>
            <div><dt>Price</dt><dd>{{ $plan['display'] }}</dd></div>
            <div><dt>Trial</dt><dd>{{ $trialDays }} days free</dd></div>
            <div><dt>Charged to</dt><dd>{{ ucfirst($billedBy) }}</dd></div>
        </dl>
        <ul class="app-benefits">
            <li>Unlimited follows, saved searches and shooting log</li>
            <li>Printable attendance record and CSV</li>
            <li>Up to {{ config('plans.pro.household_profiles') }} shooters in one household</li>
        </ul>
        <div class="app-legal">
            <p>Payment is charged to {{ $billedBy }} when you confirm. The plan renews on its own unless you cancel at least 24 hours before the period ends.</p>
            <p>Cancel in the app, or in {{ $store }} subscriptions. On the website the trial takes no card; in the stores it becomes a paid plan unless you cancel first.</p>
            <p><a href="{{ route('privacy') }}">Privacy policy</a> · <a href="{{ route('terms') }}">Terms of use</a></p>
        </div>
        <div class="app-stack">
            <a class="app-btn" href="{{ $app('subscription', ['started' => 1]) }}">Start {{ $trialDays }}-day free trial</a>
            <a class="app-btn secondary" href="{{ $app('subscription', ['restored' => 1]) }}">Restore purchases</a>
        </div>
    </div>

@elseif ($screen === 'cancel')
    <div class="app-pad">
        <h1 class="app-hero">Cancel Pro?</h1>
        <ul class="app-list plain">
            <li>Pro stays on until {{ $renews }}.</li>
            <li>You won't be charged again.</li>
            <li>Your follows, saved searches and shooting log stay.</li>
            <li>After {{ $renews }} the free limits apply: {{ $freeFollows }} follows, {{ $freeSearches }} saved search, {{ $freeLog }} log entries.</li>
        </ul>
        <div class="app-stack">
            <a class="app-btn" href="{{ $app('cancelled') }}">Cancel subscription</a>
            <a class="app-btn secondary" href="{{ $app('subscription') }}">Keep Pro</a>
        </div>
    </div>

@elseif ($screen === 'cancelled')
    <div class="app-pad">
        <p class="app-kicker">Done</p>
        <h1 class="app-hero">You're cancelled</h1>
        <p class="app-lead">Pro stays on until {{ $renews }}. No more charges.</p>
        <div class="app-stack">
            <a class="app-btn" href="{{ $app('you') }}">Back to You</a>
            <a class="app-btn secondary" href="{{ $app('plans') }}">See plans</a>
        </div>
    </div>
@endif
