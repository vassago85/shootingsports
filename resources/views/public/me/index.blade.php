<x-layouts.public
    title="My home"
    description="Your shooter dashboard: next matches, log, memberships, and the Manage area for any clubs, shops, ranges, or media you work with."
    robots="noindex, nofollow"
>
    @php
        $planClass = $planIsPro ? 'pro' : ($onTrial ? 'trial' : '');
    @endphp
    <main id="main">
        <section class="page-hero">
            <div class="wrap">
                <p class="label">My home</p>
                <h1>Welcome back, {{ $user->name }}</h1>
                <div class="me-hero-row">
                    <span class="me-plan {{ $planClass }}">{{ $planLabel }}</span>
                    @if ($onTrial)
                        <span class="me-plan trial">Trial · {{ $user->trialDaysRemaining() }} days left</span>
                    @endif
                    @if (! $planIsPro && ! $onTrial && config('plans.pro_enabled'))
                        <a href="{{ route('upgrade') }}" class="me-upgrade-inline">Upgrade to Pro →</a>
                    @endif
                </div>
            </div>
        </section>

        @if ($hasDeskAccess)
            <section class="me-section" aria-labelledby="me-manage-heading">
                <div class="wrap">
                    <div class="me-section-head">
                        <p class="label" id="me-manage-heading">Manage</p>
                        <p class="muted">Light actions here. Heavy desk work opens on the website.</p>
                    </div>

                    <div class="me-grid">
                        @foreach ($capabilityCards as $card)
                            <article class="me-card" data-test="capability-card" data-kind="{{ $card['kind'] }}">
                                <h3>
                                    {{ $card['title'] }}
                                    <span class="me-card-kind">{{ $card['kind'] }}</span>
                                </h3>
                                <p class="muted">{{ $card['summary'] }}</p>
                                @if (! empty($card['items']))
                                    <ul class="me-card-items">
                                        @foreach ($card['items'] as $item)
                                            <li><a href="{{ $item['url'] }}">{{ $item['name'] }}</a></li>
                                        @endforeach
                                    </ul>
                                @endif
                                <div class="me-card-foot">
                                    <a class="btn ghost" href="{{ $card['primary_action']['url'] }}">{{ $card['primary_action']['label'] }}</a>
                                </div>
                            </article>
                        @endforeach
                    </div>
                </div>
            </section>
        @endif

        <section class="me-section" aria-labelledby="me-shooting-heading">
            <div class="wrap">
                <div class="me-section-head">
                    <p class="label" id="me-shooting-heading">Shooting</p>
                </div>

                <div class="me-grid">
                    <a class="me-card is-link" href="{{ route('my-calendar') }}">
                        <h3>My calendar</h3>
                        <p class="muted">
                            {{ $savedCount }} {{ \Illuminate\Support\Str::plural('match', $savedCount) }} saved
                        </p>
                        @if ($nextSavedEvents->isNotEmpty())
                            <ul class="me-saved-list" aria-label="Next saved matches">
                                @foreach ($nextSavedEvents as $event)
                                    <li>
                                        <time datetime="{{ $event->starts_at->toIso8601String() }}">
                                            {{ $event->starts_at->format('j M') }}
                                        </time>
                                        <div>
                                            <div class="me-saved-title">{{ $event->title }}</div>
                                            @if ($event->venue)
                                                <div class="me-saved-venue">{{ $event->venue->name }}{{ $event->venue->town ? ' · '.$event->venue->town : '' }}</div>
                                            @endif
                                        </div>
                                    </li>
                                @endforeach
                            </ul>
                        @endif
                    </a>

                    <a class="me-card is-link" href="{{ route('my-log') }}">
                        <h3>My log</h3>
                        <p class="muted">
                            {{ $logCount }}@if ($logLimit !== null)/{{ $logLimit }}@endif logged
                            @if ($logLimit !== null && $logCount >= $logLimit)
                                · <span style="color:var(--brass-dk)">Pro removes the cap</span>
                            @endif
                        </p>
                    </a>

                    <a class="me-card is-link" href="{{ route('my-memberships') }}">
                        <h3>My memberships</h3>
                        <p class="muted">Club memberships, invoices, payments</p>
                    </a>

                    <a class="me-card is-link" href="{{ route('settings.notifications') }}">
                        <h3>Settings</h3>
                        <p class="muted">Notifications, profile, email</p>
                    </a>
                </div>
            </div>
        </section>

        @if (! $planIsPro && config('plans.pro_enabled'))
            <section class="me-section">
                <div class="wrap">
                    <div class="me-upgrade-strip">
                        <div>
                            <h2>Pro removes the limits</h2>
                            <p>Unlimited follows, unlimited log history, dedicated-status export, household up to four.</p>
                        </div>
                        <a class="btn" href="{{ route('upgrade') }}">See Pro</a>
                    </div>
                </div>
            </section>
        @endif
    </main>
</x-layouts.public>
