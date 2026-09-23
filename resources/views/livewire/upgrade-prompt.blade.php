<div>
    @if ($as === 'cutoff-row')
        {{-- Inline cut-off row (used on /my-calendar past history). The
             history string is asserted by PlanCapsTest and must stay
             stable. --}}
        <div class="history-cutoff" role="note">
            <p>Earlier matches are in your Pro history.</p>
            @if ($proEnabled)
                <button
                    type="button"
                    class="btn ghost"
                    wire:click="$dispatch('open-upgrade-prompt', { trigger: 'history_window' })"
                >See Pro</button>
            @endif
        </div>
    @endif

    @if ($open)
        <div
            class="upgrade-prompt"
            role="dialog"
            aria-modal="true"
            aria-labelledby="upgrade-prompt-title"
            wire:key="upgrade-{{ $trigger }}"
        >
            <div class="upgrade-prompt-backdrop" wire:click="close" aria-hidden="true"></div>

            <div class="upgrade-prompt-panel">
                <button
                    type="button"
                    class="upgrade-prompt-close"
                    wire:click="close"
                    aria-label="Close"
                >&times;</button>

                @if ($submitted)
                    <p class="label">Thanks</p>
                    <h2 id="upgrade-prompt-title">You are on the waitlist.</h2>
                    <p>We will email you when Pro is live. No card, no charge, no auto-signup.</p>
                    <div class="upgrade-prompt-actions">
                        <button type="button" class="btn" wire:click="close">Close</button>
                    </div>
                @else
                    @if ($proEnabled)
                        <p class="label">Pro · {{ $pricing['monthly']['display'] ?? '' }} · {{ $pricing['annual']['display'] ?? '' }}</p>
                    @else
                        <p class="label">Free limit</p>
                    @endif
                    <h2 id="upgrade-prompt-title">{{ $copy['headline'] }}</h2>
                    <p>{{ $copy['detail'] }}</p>

                    @if (! $proEnabled)
                        <div class="upgrade-prompt-actions" style="margin-top:18px">
                            <button type="button" class="btn" wire:click="close">Close</button>
                        </div>
                    @elseif (! $paystackReady)
                        {{-- Waitlist mode. Pro is not yet purchasable, we
                             are gathering demand signal via the enquiries
                             inbox. --}}
                        <label class="field" style="margin-top:14px">
                            <span>What would this need to do to be worth R30 a month to you? (optional)</span>
                            <textarea
                                wire:model.defer="answer"
                                rows="3"
                                placeholder="e.g. remember matches from 2 years ago; follow more than 3 clubs; export a printable season for RO briefings"
                            ></textarea>
                        </label>

                        <div class="upgrade-prompt-actions">
                            @auth
                                <button
                                    type="button"
                                    class="btn"
                                    wire:click="notify"
                                >Notify me when Pro launches</button>
                            @else
                                <a
                                    class="btn"
                                    href="{{ route('login') }}"
                                >Sign in to join the waitlist</a>
                            @endauth
                            <button type="button" class="btn ghost" wire:click="close">Not now</button>
                        </div>

                        <p class="upgrade-prompt-fineprint">
                            No payment page. Nothing charges. We only email when Pro is ready.
                        </p>
                    @else
                        {{-- Paystack is wired up — real upgrade CTA. --}}
                        <div class="upgrade-prompt-actions" style="margin-top:18px">
                            @auth
                                <a class="btn" href="{{ route('upgrade') }}">Go Pro now &rarr;</a>
                            @else
                                <a class="btn" href="{{ route('login') }}">Sign in to upgrade</a>
                            @endauth
                            <button type="button" class="btn ghost" wire:click="close">Not now</button>
                        </div>

                        <p class="upgrade-prompt-fineprint">
                            You will pick monthly or annual on the next screen. Cancel any time.
                        </p>
                    @endif
                @endif
            </div>
        </div>
    @endif
</div>
