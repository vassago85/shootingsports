<div>
    <main id="main">
        <section class="page-hero">
            <div class="wrap">
                <p class="label">Sign up</p>
                <h1>Create your account</h1>
                <p>One login for shooters, match directors, clubs, series and suppliers. Tick what applies — you can be any combination of them.</p>
            </div>
        </section>
        <section class="block">
            <div class="wrap" style="max-width:560px">
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

                    <div style="margin-top:6px;padding:14px 16px;border:1px solid var(--rule);background:var(--surface)">
                        <p class="label" style="margin-bottom:10px">Also register me as a</p>

                        <label class="prefs-row" style="margin-bottom:10px">
                            <input type="checkbox" wire:model.live="wants_md">
                            <div>
                                <b>Match director, club or series admin</b>
                                <p>Publish matches on the calendar for a club, range, series or federation. Staff review this request (usually within one working day).</p>
                            </div>
                        </label>

                        @if ($wants_md)
                            <label class="field" style="margin:6px 0 14px 30px">
                                <span>Which club, range, series or host will you publish for? *</span>
                                <textarea wire:model="host_hint" rows="3" maxlength="500" placeholder="e.g. Pretoria Rifle &amp; Pistol Club — I run the Wednesday IPSC shoots."></textarea>
                                <small style="color:var(--slate);font-size:12px;display:block;margin-top:4px">
                                    Staff use this to approve your request — the more specific, the faster it goes through.
                                </small>
                                @error('host_hint') <span class="err">{{ $message }}</span> @enderror
                            </label>
                        @endif

                        <label class="prefs-row" style="margin-bottom:0">
                            <input type="checkbox" wire:model.live="wants_supplier">
                            <div>
                                <b>Supplier / industry business</b>
                                <p>Gunsmith, dealer, ammunition, optics, safes, instructor — anything in the shooting industry. We email a confirmation link, then you fill in your listing.</p>
                            </div>
                        </label>

                        @if ($wants_supplier)
                            <label class="field" style="margin:6px 0 0 30px">
                                <span>Business name *</span>
                                <input type="text" wire:model="business_name" maxlength="160" autocomplete="organization" placeholder="e.g. Delmas Gun Shop">
                                @error('business_name') <span class="err">{{ $message }}</span> @enderror
                            </label>
                        @endif
                    </div>

                    <label class="prefs-row" style="margin-top:14px">
                        <input type="checkbox" wire:model="start_trial">
                        <div>
                            <b>Start my 30-day Pro trial — no card needed</b>
                            <p>Unlimited follows, saved searches, full attendance log for 30 days. Auto-reverts to Free at the end, because we never asked for a card.</p>
                        </div>
                    </label>

                    <p style="margin-top:14px;color:var(--slate);font-size:0.92rem">
                        By creating an account you agree to the
                        <a href="{{ route('terms') }}" style="text-decoration:underline">Terms of Use</a>
                        and
                        <a href="{{ route('privacy') }}" style="text-decoration:underline">Privacy &amp; POPIA</a> notice.
                    </p>

                    <button type="submit" class="btn" wire:loading.attr="disabled" wire:target="register">
                        <span wire:loading.remove wire:target="register">Create account</span>
                        <span wire:loading wire:target="register">Creating…</span>
                    </button>
                </form>

                <p style="margin-top:22px;color:var(--slate)">
                    Already have an account?
                    <a href="{{ route('login') }}" style="text-decoration:underline">Log in</a>.
                </p>
            </div>
        </section>
    </main>
</div>
