<div>
    <main id="main">
        <section class="page-hero">
            <div class="wrap">
                <p class="label">Pro · Upgrade</p>
                <h1>Go Pro</h1>
                <p>Unlimited follows, unlimited saved searches, full history, season exports. Cancel any time from your account — no lock-in.</p>
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
                    {{-- === Paid Pro subscriber: cancel + status only, no pricing === --}}
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

                    <p style="margin-top:12px;color:var(--slate);font-size:13px">
                        Cancelling here stops all future charges immediately. You'll keep Pro until the end of the period you've already paid for — no proration, no surprises.
                    </p>

                @elseif ($user->isCancelling())
                    {{-- === Cancelled but still inside their paid window === --}}
                    <div class="pro-status">
                        <p class="pricing-label">Your subscription</p>
                        <p class="pricing-amount">Cancelled</p>
                        <p class="pricing-summary">Pro stays active until {{ $user->plan_expires_at?->format('j M Y') ?? 'the end of the billing period' }}. No more charges will happen.</p>
                    </div>

                    @if ($ready)
                        <p style="margin-top:22px;color:var(--slate)">Changed your mind? Start a fresh subscription below.</p>
                    @endif

                @elseif ($user->isOnTrial())
                    {{-- === On the 30-day free trial, no card yet === --}}
                    <div class="trial-progress">
                        <p class="pricing-label">Your trial</p>
                        <p class="pricing-amount"><b>You're on Pro</b> — {{ $user->trialDaysRemaining() }} {{ $user->trialDaysRemaining() === 1 ? 'day' : 'days' }} left</p>
                        <p>Trial ends {{ $user->plan_expires_at->format('j M Y') }}. Add a card below to keep Pro after that — nothing charges until the trial ends.</p>
                    </div>
                @endif

                @if (! $user->hasActiveSubscription())
                    {{-- === Trial CTA: shown to Free users who haven't trialed, regardless of
                         Paystack readiness — the trial is a local action and doesn't need
                         payment infrastructure. Kept above the pricing so it reads as the
                         primary conversion path. === --}}
                    @if ($trialEligible)
                        <div class="trial-cta">
                            <p class="label">Try Pro free · {{ $trialDays }} days · no card</p>
                            <h2>Take Pro for a spin, no card required.</h2>
                            <p>
                                Get every Pro feature for {{ $trialDays }} days. Unlimited follows, unlimited saved searches,
                                full attendance log, printable season records, CSV exports. When the trial ends you're
                                automatically back on Free — no auto-billing, ever, because we never asked for a card.
                            </p>
                            <p>
                                <button
                                    type="button"
                                    class="btn"
                                    wire:click="startTrial"
                                    wire:loading.attr="disabled"
                                    wire:target="startTrial"
                                >
                                    <span wire:loading.remove wire:target="startTrial">Start my {{ $trialDays }}-day trial</span>
                                    <span wire:loading wire:target="startTrial">Starting…</span>
                                </button>
                            </p>
                        </div>
                    @endif

                    @if (! $ready)
                        <div class="empty" style="margin-top:22px">
                            <p><b>Pro is not open for paid subscriptions yet.</b> We are still finalising the payment gateway.</p>
                            @if ($trialEligible)
                                <p style="margin-top:8px">You can still start the 30-day trial above — nothing to pay, no card required.</p>
                            @else
                                <p style="margin-top:8px">Meanwhile — sign up for the waitlist and we will email you the moment it opens.</p>
                            @endif
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
                                <span wire:loading.remove wire:target="checkout">
                                    @if ($user->isOnTrial())
                                        Add a card to keep Pro · {{ $pricing[$selected]['display'] }}
                                    @else
                                        Continue to secure checkout · {{ $pricing[$selected]['display'] }}
                                    @endif
                                </span>
                                <span wire:loading wire:target="checkout">Contacting Paystack…</span>
                            </button>
                            <p class="pro-fineprint">
                                You'll be handed off to Paystack Checkout. Your card is stored by Paystack (PCI-DSS compliant) — never on our servers.
                                Cancel any time from this page. Pro stays active until the end of the current billing period.
                            </p>
                        </div>
                    @endif
                @endif
            </div>
        </section>
    </main>
</div>
