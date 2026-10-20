<div>
    <main id="main">
        <section class="page-hero">
            <div class="wrap" style="max-width:760px">
                <p class="label">Settings</p>
                <h1>Dedicated status tracker</h1>
                <p class="lede">
                    Keep count of the matches that go toward your annual dedicated-status renewal.
                    The counting cycle runs
                    <strong>{{ $cycle['start']->format('j F') }} to {{ $cycle['end']->format('j F') }}</strong>
                    each year.
                </p>
            </div>
        </section>

        <section class="block">
            <div class="wrap" style="max-width:760px">
                @if (session('status'))
                    <div class="ded-notice ded-notice-success" role="status" aria-live="polite">
                        {{ session('status') }}
                    </div>
                @endif

                @if (! $user->isPro())
                    <div class="ded-notice ded-notice-pro">
                        <strong>The progress widget and printable report are Pro features.</strong>
                        <p>You can fill in your details now so they're ready the moment Pro opens.</p>
                    </div>
                @endif

                <form wire:submit.prevent="save" class="ded-form" novalidate>
                    <div class="ded-form-card">
                        <div class="ded-field">
                            <label for="ded-body"><b>Accrediting body</b></label>
                            <p class="ded-help">The association that holds your dedicated status.</p>
                            <select id="ded-body" wire:model="body" class="ded-input">
                                @foreach ($bodies as $code => $label)
                                    <option value="{{ $code }}">{{ $label }}</option>
                                @endforeach
                            </select>
                            @error('body')<span class="ded-error">{{ $message }}</span>@enderror
                        </div>

                        <div class="ded-field">
                            <label for="ded-membership"><b>Membership number</b></label>
                            <p class="ded-help">Your association membership number, as it appears on your card.</p>
                            <input id="ded-membership" type="text" wire:model="membershipNumber"
                                maxlength="60" class="ded-input" autocomplete="off">
                            @error('membershipNumber')<span class="ded-error">{{ $message }}</span>@enderror
                        </div>

                        <div class="ded-field">
                            <label for="ded-status"><b>Dedicated status number</b></label>
                            <p class="ded-help">The identifier your association issues on the dedicated-status certificate.</p>
                            <input id="ded-status" type="text" wire:model="statusNumber"
                                maxlength="60" class="ded-input" autocomplete="off">
                            @error('statusNumber')<span class="ded-error">{{ $message }}</span>@enderror
                        </div>

                        <div class="ded-field">
                            <label for="ded-category"><b>Dedicated status for</b></label>
                            <p class="ded-help">What your dedicated status covers. Shown on the licence supporting document.</p>
                            <select id="ded-category" wire:model="category" class="ded-input">
                                <option value="">Select one…</option>
                                @foreach ($categories as $code => $label)
                                    <option value="{{ $code }}">{{ $label }}</option>
                                @endforeach
                            </select>
                            @error('category')<span class="ded-error">{{ $message }}</span>@enderror
                        </div>

                        <div class="ded-field">
                            <label for="ded-required"><b>Activities required per cycle</b></label>
                            <p class="ded-help">Minimum two per year. Some associations ask for more.</p>
                            <input id="ded-required" type="number" min="2" max="365"
                                wire:model="requiredCount" class="ded-input ded-input-narrow">
                            @error('requiredCount')<span class="ded-error">{{ $message }}</span>@enderror
                        </div>
                    </div>

                    <div class="ded-actions">
                        <button type="submit" class="btn" wire:loading.attr="disabled" wire:target="save">
                            <span wire:loading.remove wire:target="save">Save</span>
                            <span wire:loading wire:target="save">Saving…</span>
                        </button>
                        @if ($user->dedicated_body || $user->dedicated_membership_number || $user->dedicated_status_number)
                            <button type="button" class="btn ghost" wire:click="clear"
                                wire:confirm="Clear your dedicated-status settings?">
                                Clear
                            </button>
                        @endif
                        <a class="btn ghost" href="{{ route('my-log') }}">Back to my log</a>
                    </div>
                </form>
            </div>
        </section>
    </main>
</div>
