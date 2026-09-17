<div>
    <main id="main">
        <section class="page-hero">
            <div class="wrap">
                <p class="label">Sign up · Match director</p>
                <h1>Register as a match director</h1>
                <p>Publish matches for a club, range, or host on Shooting Sports. Free to list. <b>New requests are reviewed by staff</b> — usually within one working day — before you can post events. Your shooter account works immediately.</p>
            </div>
        </section>
        <section class="block">
            <div class="wrap" style="max-width:520px">
                <form wire:submit="register" class="enquiry-form" novalidate>
                    <label class="field">
                        <span>Your name</span>
                        <input type="text" wire:model="name" required autofocus autocomplete="name" maxlength="120">
                        @error('name') <span class="err">{{ $message }}</span> @enderror
                    </label>
                    <label class="field">
                        <span>Email</span>
                        <input type="email" wire:model="email" required autocomplete="email" maxlength="255">
                        @error('email') <span class="err">{{ $message }}</span> @enderror
                    </label>
                    <label class="field">
                        <span>Password (min 8 characters)</span>
                        <input type="password" wire:model="password" required autocomplete="new-password" maxlength="255">
                        @error('password') <span class="err">{{ $message }}</span> @enderror
                    </label>
                    <label class="field">
                        <span>Confirm password</span>
                        <input type="password" wire:model="password_confirmation" required autocomplete="new-password" maxlength="255">
                    </label>
                    <label class="field">
                        <span>Which club, range, series or host will you publish for? *</span>
                        <textarea wire:model="host_hint" rows="3" maxlength="500" required placeholder="e.g. Pretoria Rifle &amp; Pistol Club — I run the Wednesday IPSC shoots. Or: Muletech Ridge Range — I host the Steel Challenge series."></textarea>
                        <small style="color:var(--slate);font-size:12px;display:block;margin-top:4px">
                            This is what staff use to approve your request — the more specific, the faster it goes through.
                        </small>
                        @error('host_hint') <span class="err">{{ $message }}</span> @enderror
                    </label>

                    <label class="prefs-row" style="margin-top:6px">
                        <input type="checkbox" wire:model="start_trial">
                        <div>
                            <b>Start my 30-day Pro trial — no card needed</b>
                            <p>Runs on your personal shooter account (separate from your MD role). Unlimited follows, saved searches, full attendance log for 30 days. Auto-reverts to Free at the end, because we never asked for a card.</p>
                        </div>
                    </label>

                    <p style="margin-top:14px;color:var(--slate);font-size:0.92rem">
                        By submitting you agree to the
                        <a href="{{ route('terms') }}">Terms of Use</a>
                        and
                        <a href="{{ route('privacy') }}">Privacy &amp; POPIA</a> notice.
                    </p>

                    <button type="submit" class="btn" wire:loading.attr="disabled" wire:target="register">
                        <span wire:loading.remove wire:target="register">Submit for review</span>
                        <span wire:loading wire:target="register">Submitting…</span>
                    </button>
                </form>

                <p style="margin-top:22px;color:var(--slate)">
                    Already have an account?
                    <a href="{{ route('login') }}">Log in</a>.
                    Just want to save matches you're going to?
                    <a href="{{ route('register') }}">Create a shooter account instead</a>.
                </p>
            </div>
        </section>
    </main>
</div>
