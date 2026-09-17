<div>
    <main id="main">
        <section class="page-hero">
            <div class="wrap">
                <p class="label">Sign up · Shooter</p>
                <h1>Create your shooter account</h1>
                <p>Follow your clubs and disciplines, save searches, keep every match you're going to in one place. Free forever for shooters.</p>
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

                    <label class="prefs-row" style="margin-top:6px">
                        <input type="checkbox" wire:model="start_trial">
                        <div>
                            <b>Start my 30-day Pro trial — no card needed</b>
                            <p>Get unlimited follows, saved searches, and the full attendance log for 30 days. Auto-reverts to Free at the end, because we never asked for a card. You can start it later from /upgrade too.</p>
                        </div>
                    </label>

                    <p style="margin-top:14px;color:var(--slate);font-size:0.92rem">
                        By creating an account you agree to the
                        <a href="{{ route('terms') }}">Terms of Use</a>
                        and
                        <a href="{{ route('privacy') }}">Privacy &amp; POPIA</a> notice.
                    </p>

                    <button type="submit" class="btn" wire:loading.attr="disabled" wire:target="register">
                        <span wire:loading.remove wire:target="register">Create account</span>
                        <span wire:loading wire:target="register">Creating…</span>
                    </button>
                </form>

                <p style="margin-top:22px;color:var(--slate)">
                    Already have an account?
                    <a href="{{ route('login') }}">Log in</a>.
                    Running matches yourself?
                    <a href="{{ route('directors.register') }}">Register as a match director instead</a>.
                </p>
            </div>
        </section>
    </main>
</div>
