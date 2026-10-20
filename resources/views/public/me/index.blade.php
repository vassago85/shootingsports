<x-layouts.public
    title="My home"
    description="Your shooter dashboard: next matches, log, memberships, and the Manage area for any clubs, shops, ranges, or media you work with."
    robots="noindex, nofollow"
>
    <main id="main">
        <section class="page-hero">
            <div class="wrap">
                <p class="label">My home</p>
                <h1>Welcome back, {{ $user->name }}</h1>
                <p>
                    <span class="tag" style="background:transparent;border:1px solid var(--line)">{{ $planLabel }}</span>
                    @if ($onTrial)
                        <span class="tag" style="background:transparent;border:1px solid var(--brass);margin-left:6px">Trial · {{ $user->trialDaysRemaining() }} days left</span>
                    @endif
                </p>
            </div>
        </section>

        @if ($hasDeskAccess)
            <section class="block" aria-labelledby="me-manage-heading">
                <div class="wrap">
                    <h2 id="me-manage-heading" class="section-title">Manage</h2>
                    <p class="muted" style="margin-top:-6px">Light actions live here. Members, invoices, magazine, and billing open on the website.</p>

                    <div class="me-manage-grid" style="display:grid;grid-template-columns:repeat(auto-fit,minmax(240px,1fr));gap:14px;margin-top:14px">
                        @foreach ($capabilityCards as $card)
                            <article
                                class="me-card"
                                data-test="capability-card"
                                data-kind="{{ $card['kind'] }}"
                                style="border:1px solid var(--line);border-radius:10px;padding:16px;display:flex;flex-direction:column;gap:10px"
                            >
                                <header style="display:flex;justify-content:space-between;align-items:baseline">
                                    <h3 style="margin:0">{{ $card['title'] }}</h3>
                                    <span class="tag" style="background:transparent;border:1px solid var(--line);font-size:12px">{{ $card['kind'] }}</span>
                                </header>
                                <p class="muted" style="margin:0">{{ $card['summary'] }}</p>
                                @if (! empty($card['items']))
                                    <ul style="list-style:none;padding:0;margin:0;display:grid;gap:6px">
                                        @foreach ($card['items'] as $item)
                                            <li style="font-family:var(--f-mono);font-size:14px">
                                                <a href="{{ $item['url'] }}" style="color:inherit;text-decoration:none;border-bottom:1px solid var(--line)">{{ $item['name'] }}</a>
                                            </li>
                                        @endforeach
                                    </ul>
                                @endif
                                <div style="margin-top:auto">
                                    <a class="btn ghost" href="{{ $card['primary_action']['url'] }}">{{ $card['primary_action']['label'] }}</a>
                                </div>
                            </article>
                        @endforeach
                    </div>
                </div>
            </section>
        @endif

        <section class="block">
            <div class="wrap">
                <h2 class="section-title">Shooting</h2>
                <div class="me-manage-grid" style="display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:14px">
                    <a class="me-card" href="{{ route('my-calendar') }}" style="display:block;border:1px solid var(--line);border-radius:10px;padding:16px;text-decoration:none;color:inherit">
                        <h3 style="margin:0 0 6px">My calendar</h3>
                        <p class="muted" style="margin:0">
                            {{ $savedCount }} {{ \Illuminate\Support\Str::plural('match', $savedCount) }} saved
                        </p>
                    </a>

                    <a class="me-card" href="{{ route('my-log') }}" style="display:block;border:1px solid var(--line);border-radius:10px;padding:16px;text-decoration:none;color:inherit">
                        <h3 style="margin:0 0 6px">My log</h3>
                        <p class="muted" style="margin:0">
                            {{ $logCount }}@if ($logLimit !== null)/{{ $logLimit }}@endif logged
                            @if ($logLimit !== null && $logCount >= $logLimit)
                                · <span style="color:var(--brass)">Pro removes the cap</span>
                            @endif
                        </p>
                    </a>

                    <a class="me-card" href="{{ route('my-memberships') }}" style="display:block;border:1px solid var(--line);border-radius:10px;padding:16px;text-decoration:none;color:inherit">
                        <h3 style="margin:0 0 6px">My memberships</h3>
                        <p class="muted" style="margin:0">Club memberships, invoices, payments</p>
                    </a>

                    <a class="me-card" href="{{ route('settings.notifications') }}" style="display:block;border:1px solid var(--line);border-radius:10px;padding:16px;text-decoration:none;color:inherit">
                        <h3 style="margin:0 0 6px">Settings</h3>
                        <p class="muted" style="margin:0">Notifications, profile, email</p>
                    </a>
                </div>
            </div>
        </section>

        @if (! $planIsPro && config('plans.pro_enabled'))
            <section class="block">
                <div class="wrap" style="border:1px solid var(--brass);border-radius:10px;padding:18px">
                    <h2 style="margin:0 0 6px">Pro removes the limits</h2>
                    <p class="muted" style="margin:0 0 10px">
                        Unlimited follows, unlimited log history, dedicated-status export, household up to four.
                    </p>
                    <a class="btn" href="{{ route('upgrade') }}">See Pro</a>
                </div>
            </section>
        @endif
    </main>
</x-layouts.public>
