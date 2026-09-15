<div>
    <main id="main">
        <section class="page-hero">
            <div class="wrap">
                <p class="label">Pro · Upgrade</p>
                <h1>Go Pro</h1>
                <p>Unlimited follows, unlimited saved searches, full history, season exports. Cancel any time from your account.</p>
            </div>
        </section>

        <section class="block">
            <div class="wrap" style="max-width:820px">
                @if (session('status'))
                    <div class="empty" style="border-color:var(--brass);margin-bottom:18px">
                        {{ session('status') }}
                    </div>
                @endif

                @if ($user->hasActiveSubscription())
                    {{-- Active subscriber — cancel + status only, no pricing --}}
                    <div class="pro-status">
                        <p class="pricing-label">Your subscription</p>
                        <p class="pricing-amount">You're on Pro</p>
                        <p class="pricing-summary">{{ $user->subscriptionStatusLabel() }}</p>
                        @if ($user->plan_expires_at)
                            <p style="margin-top:8px;color:var(--slate)">Next billing date: {{ $user->plan_expires_at->format('j M Y') }}</p>
                        @endif
                    </div>

                    <form method="post" action="{{ route('upgrade.cancel') }}" onsubmit="return confirm('Cancel Pro? You will keep access until the end of the current billing period.');" style="margin-top:22px">
                        @csrf
                        <button type="submit" class="btn ghost">Cancel subscription</button>
                    </form>
                @elseif ($user->isCancelling())
                    {{-- Cancelled but still inside their paid window --}}
                    <div class="pro-status">
                        <p class="pricing-label">Your subscription</p>
                        <p class="pricing-amount">Cancelled</p>
                        <p class="pricing-summary">Pro stays active until {{ $user->plan_expires_at?->format('j M Y') ?? 'the end of the billing period' }}. No more charges will happen.</p>
                    </div>

                    @if ($ready)
                        <p style="margin-top:22px;color:var(--slate)">Changed your mind? Start a fresh subscription below.</p>
                    @endif
                @endif

                @if (! $user->hasActiveSubscription())
                    @if (! $ready)
                        <div class="empty" style="margin-top:22px">
                            <p><b>Pro is not open for subscriptions yet.</b> We are still finalising the payment gateway.</p>
                            <p style="margin-top:8px">Meanwhile — sign up for the waitlist and we will email you the moment it opens.</p>
                            <p style="margin-top:14px">
                                <a class="btn" href="{{ route('my-calendar') }}">Back to my calendar</a>
                            </p>
                        </div>
                    @else
                        <div class="pricing-grid" style="margin-top:22px">
                            <button
                                type="button"
                                wire:click="pick('annual')"
                                class="pricing-card {{ $selected === 'annual' ? 'is-selected' : '' }}"
                                aria-pressed="{{ $selected === 'annual' ? 'true' : 'false' }}"
                            >
                                <p class="pricing-label">Annual</p>
                                <p class="pricing-amount">{{ $pricing['annual']['display'] }}</p>
                                <p class="pricing-summary">{{ $pricing['annual']['summary'] }}</p>
                            </button>

                            <button
                                type="button"
                                wire:click="pick('monthly')"
                                class="pricing-card {{ $selected === 'monthly' ? 'is-selected' : '' }}"
                                aria-pressed="{{ $selected === 'monthly' ? 'true' : 'false' }}"
                            >
                                <p class="pricing-label">Monthly</p>
                                <p class="pricing-amount">{{ $pricing['monthly']['display'] }}</p>
                                <p class="pricing-summary">{{ $pricing['monthly']['summary'] }}</p>
                            </button>
                        </div>

                        <ul class="pro-features">
                            <li><b>Attendance log</b> — track every match you shoot, no cap on entries</li>
                            <li><b>Annual attendance record</b> — printable PDF + CSV for your SAPSA / PISA / KKSA dedicated-status renewals</li>
                            <li>Unlimited club, discipline and venue follows</li>
                            <li>Unlimited saved calendar searches</li>
                            <li>Full match history back to when we started tracking</li>
                        </ul>

                        <div class="pro-actions">
                            <button
                                type="button"
                                class="btn"
                                wire:click="checkout"
                                wire:loading.attr="disabled"
                                wire:target="checkout"
                            >
                                <span wire:loading.remove wire:target="checkout">Continue to secure checkout · {{ $pricing[$selected]['display'] }}</span>
                                <span wire:loading wire:target="checkout">Contacting Paystack…</span>
                            </button>
                            <p class="pro-fineprint">
                                You will be handed off to Paystack Checkout. Your card is stored by Paystack (PCI-DSS compliant) — never on our servers. Cancel any time from your account and Pro stays active until the end of the current billing period.
                            </p>
                        </div>
                    @endif
                @endif
            </div>
        </section>
    </main>
</div>
